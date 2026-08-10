<?php

declare(strict_types=1);

namespace Ping;

final readonly class Config
{
    public function __construct(
        public string $databasePath,
        public string $uploadPath,
        public string $outputPath,
        public string $printerMode,
        public string $printerName,
        public int $maxMessageLength,
        public int $maxImageBytes,
        public int $maxImagePixels,
        public int $printerWidth,
        public int $rateLimit,
        public int $rateWindowSeconds,
        public string $timezone,
    ) {
    }

    public static function fromEnvironment(string $root): self
    {
        return new self(
            databasePath: self::env('PING_DATABASE', $root . '/storage/ping.sqlite'),
            uploadPath: self::env('PING_UPLOAD_PATH', $root . '/storage/uploads'),
            outputPath: self::env('PING_OUTPUT_PATH', $root . '/storage/print-output'),
            printerMode: self::env('PING_PRINTER_MODE', 'file'),
            printerName: self::env('PING_PRINTER_NAME', 'StarTSP100'),
            maxMessageLength: self::envInt('PING_MAX_MESSAGE_LENGTH', 1024),
            maxImageBytes: self::envInt('PING_MAX_IMAGE_BYTES', 5 * 1024 * 1024),
            maxImagePixels: self::envInt('PING_MAX_IMAGE_PIXELS', 20_000_000),
            printerWidth: self::envInt('PING_PRINTER_WIDTH', 512),
            rateLimit: self::envInt('PING_RATE_LIMIT', 10),
            rateWindowSeconds: self::envInt('PING_RATE_WINDOW', 60),
            timezone: self::env('PING_TIMEZONE', 'America/New_York'),
        );
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function envInt(string $name, int $default): int
    {
        $value = getenv($name);
        return is_string($value) && ctype_digit($value) ? (int) $value : $default;
    }
}
