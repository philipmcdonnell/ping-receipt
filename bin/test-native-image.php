<?php
declare(strict_types=1);

use Ping\Config;

require dirname(__DIR__) . '/vendor/autoload.php';
if (($argv[1] ?? '') !== '--yes') {
    fwrite(STDERR, "This command operates the physical printer. Re-run with --yes.\n");
    exit(2);
}

$root = dirname(__DIR__);
$config = Config::fromEnvironment($root);
$path = $config->uploadPath . '/native-image-test.png';
if (!is_dir($config->uploadPath) && !mkdir($config->uploadPath, 0770, true) && !is_dir($config->uploadPath)) {
    throw new RuntimeException('Unable to create printer-test storage.');
}

$image = imagecreatetruecolor(512, 360);
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
imagefill($image, 0, 0, $white);
imagesetthickness($image, 7);
imagerectangle($image, 8, 8, 503, 351, $black);
imagefilledellipse($image, 95, 180, 125, 125, $black);
imagefilledrectangle($image, 185, 110, 475, 250, $black);
imagestring($image, 5, 265, 170, 'NATIVE IMAGE', $white);
imagepng($image, $path, 7);
imagedestroy($image);

try {
    $command = [
        'powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
        '-File', $root . '/bin/print-image.ps1',
        '-ImagePath', $path,
        '-PrinterName', $config->printerName,
    ];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the Windows image printer.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException(trim($stderr ?: $stdout) ?: 'Windows image printing failed.');
    }
    echo "One native Windows image test was submitted to {$config->printerName}.\n";
} finally {
    if (is_file($path)) {
        @unlink($path);
    }
}
