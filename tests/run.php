<?php
declare(strict_types=1);

use Ping\Database;
use Ping\FileReceiptPrinter;
use Ping\ImageProcessor;
use Ping\MessageValidator;
use Ping\NativeReceiptRenderer;
use Ping\PingService;
use Ping\RateLimiter;
use Ping\ReceiptRepository;
use Ping\ValidationException;

require dirname(__DIR__) . '/vendor/autoload.php';

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};
$expectValidation = static function (callable $callback, string $message) use ($assert): void {
    try {
        $callback();
    } catch (ValidationException) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};
$deleteTree = static function (string $path) use (&$deleteTree): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (new FilesystemIterator($path) as $item) {
        $item->isDir() ? $deleteTree($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
};

$temp = sys_get_temp_dir() . '/ping-tests-' . bin2hex(random_bytes(6));
mkdir($temp, 0770, true);
$pdo = $repository = $limiter = $service = null;

try {
    $validator = new MessageValidator(20);
    $assert($validator->validate("Hello\r\nPhil", false) === "Hello\nPhil", 'normalizes line endings');
    $assert($validator->validate("Phil’s PING", false) === "Phil's PING", 'normalizes smart punctuation');
    $expectValidation(fn () => $validator->validate("Hello\x1B@", false), 'rejects ESC/POS control bytes');
    $expectValidation(fn () => $validator->validate('Hello 😀', false), 'rejects unsupported Unicode');
    $expectValidation(fn () => $validator->validate('', false), 'requires text or an image');
    $assert($validator->validate('', true) === '', 'allows an image-only PING');

    $source = imagecreatetruecolor(1200, 600);
    $background = imagecolorallocate($source, 240, 240, 240);
    $foreground = imagecolorallocate($source, 20, 20, 20);
    imagefill($source, 0, 0, $background);
    imagefilledellipse($source, 600, 300, 500, 350, $foreground);
    $sourcePath = $temp . '/source.png';
    imagepng($source, $sourcePath);
    imagedestroy($source);

    $processor = new ImageProcessor($temp . '/uploads', 5 * 1024 * 1024, 20_000_000, 512);
    $processed = $processor->processPath($sourcePath);
    [$width, $height] = getimagesize($processed);
    $assert($width === 512 && $height === 256, 'resizes images to printer width while preserving aspect ratio');
    $assert((new finfo(FILEINFO_MIME_TYPE))->file($processed) === 'image/png', 'normalizes images to PNG');

    $nativeReceipt = $temp . '/native-receipt.png';
    (new NativeReceiptRenderer('America/New_York'))->render('12345', 'Native receipt test', $processed, $nativeReceipt);
    [$nativeWidth, $nativeHeight] = getimagesize($nativeReceipt);
    $assert($nativeWidth === 512 && $nativeHeight > 400, 'renders a complete native Windows receipt with text and image');

    $pdo = Database::connect($temp . '/ping.sqlite');
    $repository = new ReceiptRepository($pdo);
    $limiter = new RateLimiter($pdo, 2, 60);
    $assert($limiter->consume('client', 120), 'allows first request');
    $assert($limiter->consume('client', 120), 'allows request at limit');
    $assert(!$limiter->consume('client', 120), 'rejects request over limit');

    $service = new PingService(
        new MessageValidator(),
        new ImageProcessor($temp . '/uploads', 5 * 1024 * 1024, 20_000_000, 512),
        $repository,
        new RateLimiter($pdo, 10, 60),
        new FileReceiptPrinter($temp . '/output'),
    );
    $transaction = $service->send('A test PING', null, 'service-client');
    $assert((bool) preg_match('/^\d{5}$/', $transaction), 'generates a five-digit transaction number server-side');
    $row = $pdo->query('SELECT * FROM pings ORDER BY id DESC LIMIT 1')->fetch();
    $assert($row['status'] === 'printed' && $row['printed_at'] !== null, 'records successful print state');
    $assert(count(glob($temp . '/output/*.txt')) === 1, 'writes fake-printer receipt output');

    echo "PASS: {$tests} assertions\n";
} finally {
    $service = $repository = $limiter = $pdo = null;
    gc_collect_cycles();
    usleep(50_000);
    $deleteTree($temp);
}
