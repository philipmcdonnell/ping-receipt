<?php

declare(strict_types=1);

namespace Ping;

final class FileReceiptPrinter implements PrinterDriver
{
    public function __construct(private readonly string $outputPath)
    {
    }

    public function print(string $transaction, string $message, ?string $imagePath): void
    {
        if (!is_dir($this->outputPath) && !mkdir($this->outputPath, 0770, true) && !is_dir($this->outputPath)) {
            throw new \RuntimeException('Unable to create fake-printer output storage.');
        }
        $base = $this->outputPath . '/' . gmdate('Ymd-His') . '-' . $transaction;
        $receipt = "PING\nMESSAGE FOR PHIL MCDONNELL\n" . str_repeat('-', 42) . "\n";
        $receipt .= 'TIMESTAMP: ' . gmdate('c') . "\nTRANSACTION #: {$transaction}\n\n";
        if ($message !== '') {
            $receipt .= $message . "\n";
        }
        if ($imagePath !== null) {
            $imageCopy = $base . '.png';
            if (!copy($imagePath, $imageCopy)) {
                throw new \RuntimeException('Unable to copy the image to fake-printer output.');
            }
            $receipt .= "\n[IMAGE: " . basename($imageCopy) . "]\n";
        }
        if (file_put_contents($base . '.txt', $receipt, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write fake-printer output.');
        }
    }
}
