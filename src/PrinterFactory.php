<?php

declare(strict_types=1);

namespace Ping;

final class PrinterFactory
{
    public static function create(Config $config): PrinterDriver
    {
        return match ($config->printerMode) {
            'windows' => new NativeWindowsReceiptPrinter(
                $config->printerName,
                $config->uploadPath,
                $config->timezone,
                dirname(__DIR__) . '/bin/print-image.ps1',
            ),
            'file' => new FileReceiptPrinter($config->outputPath),
            default => throw new \RuntimeException('PING_PRINTER_MODE must be either file or windows.'),
        };
    }
}
