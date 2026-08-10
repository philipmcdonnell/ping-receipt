<?php
declare(strict_types=1);

use Ping\Config;
use Ping\ImageProcessor;
use Ping\NativeWindowsReceiptPrinter;

require dirname(__DIR__) . '/vendor/autoload.php';

if (($argv[1] ?? '') !== '--yes') {
    fwrite(STDERR, "This command operates the physical receipt printer. Re-run with --yes.\n");
    exit(2);
}

$root = dirname(__DIR__);
$config = Config::fromEnvironment($root);
$sourcePath = $config->uploadPath . '/printer-test-source.png';
$processedPath = null;

if (!is_dir($config->uploadPath) && !mkdir($config->uploadPath, 0770, true) && !is_dir($config->uploadPath)) {
    throw new RuntimeException('Unable to create printer-test storage.');
}

$image = imagecreatetruecolor(900, 420);
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
imagefill($image, 0, 0, $white);
imagesetthickness($image, 8);
imagerectangle($image, 20, 20, 879, 399, $black);
imagefilledellipse($image, 160, 210, 190, 190, $black);
imagefilledrectangle($image, 300, 120, 820, 300, $black);
imagestring($image, 5, 390, 190, 'PING IMAGE TEST', $white);
imagepng($image, $sourcePath);
imagedestroy($image);

try {
    $processor = new ImageProcessor(
        $config->uploadPath,
        $config->maxImageBytes,
        $config->maxImagePixels,
        $config->printerWidth,
    );
    $processedPath = $processor->processPath($sourcePath);
    (new NativeWindowsReceiptPrinter(
        $config->printerName,
        $config->uploadPath,
        $config->timezone,
        $root . '/bin/print-image.ps1',
    ))->print(
        'TEST1',
        "Controlled hardware test\nPlain PHP + image printing",
        $processedPath,
    );
    echo "One text-and-image test receipt was submitted to {$config->printerName}.\n";
} finally {
    if (is_file($sourcePath)) {
        @unlink($sourcePath);
    }
    if ($processedPath !== null && is_file($processedPath)) {
        @unlink($processedPath);
    }
}
