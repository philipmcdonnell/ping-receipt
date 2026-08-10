<?php
declare(strict_types=1);
namespace Ping;

final class NativeWindowsReceiptPrinter implements PrinterDriver
{
    public function __construct(
        private readonly string $printerName,
        private readonly string $workingPath,
        private readonly string $timezone,
        private readonly string $helperPath,
    ) {
    }

    public function print(string $transaction, string $message, ?string $imagePath): void
    {
        if (!is_dir($this->workingPath) && !mkdir($this->workingPath, 0770, true) && !is_dir($this->workingPath)) {
            throw new \RuntimeException('Unable to create native receipt storage.');
        }
        $receiptPath = $this->workingPath . '/receipt-' . bin2hex(random_bytes(12)) . '.png';
        try {
            (new NativeReceiptRenderer($this->timezone))->render($transaction, $message, $imagePath, $receiptPath);
            $command = [
                'powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
                '-File', $this->helperPath,
                '-ImagePath', $receiptPath,
                '-PrinterName', $this->printerName,
            ];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start the Windows receipt printer.');
            }
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                throw new \RuntimeException(trim($stderr ?: $stdout) ?: 'Windows receipt printing failed.');
            }
        } finally {
            if (is_file($receiptPath)) {
                @unlink($receiptPath);
            }
        }
    }
}
