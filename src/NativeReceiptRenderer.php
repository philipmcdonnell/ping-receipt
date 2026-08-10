<?php
declare(strict_types=1);
namespace Ping;

final class NativeReceiptRenderer
{
    private const WIDTH = 512;
    private const MARGIN = 24;

    public function __construct(private readonly string $timezone)
    {
    }

    public function render(string $transaction, string $message, ?string $imagePath, string $outputPath): void
    {
        $canvas = imagecreatetruecolor(self::WIDTH, 2400);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        imagefill($canvas, 0, 0, $white);

        $regular = 'C:/Windows/Fonts/consola.ttf';
        $bold = 'C:/Windows/Fonts/consolab.ttf';
        $hasTrueType = function_exists('imagettftext') && is_file($regular) && is_file($bold);
        $y = 42;

        if ($hasTrueType) {
            $this->centerText($canvas, 'PING', $bold, 34, $y, $black);
            $y += 46;
            $this->centerText($canvas, 'MESSAGE FOR PHIL MCDONNELL', $bold, 14, $y, $black);
            $y += 34;
        } else {
            imagestring($canvas, 5, 225, $y, 'PING', $black);
            $y += 28;
            imagestring($canvas, 4, 120, $y, 'MESSAGE FOR PHIL MCDONNELL', $black);
            $y += 28;
        }

        imageline($canvas, self::MARGIN, $y, self::WIDTH - self::MARGIN, $y, $black);
        $y += 28;
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone($this->timezone)))->format('m/d/y h:i A');
        $y = $this->drawLine($canvas, "TIMESTAMP: {$timestamp}", $bold, 15, $y, $black, $hasTrueType, 25);
        $y = $this->drawLine($canvas, "TRANSACTION #: {$transaction}", $bold, 15, $y, $black, $hasTrueType, 25);
        $y += 18;

        if ($message !== '') {
            foreach ($this->wrap($message, 42) as $line) {
                $y = $this->drawLine($canvas, $line, $regular, 16, $y, $black, $hasTrueType, 25);
            }
            $y += 16;
        }

        if ($imagePath !== null) {
            $source = @imagecreatefrompng($imagePath);
            if ($source === false) {
                imagedestroy($canvas);
                throw new \RuntimeException('The processed receipt image could not be loaded.');
            }
            $availableWidth = self::WIDTH - (self::MARGIN * 2);
            $scale = min($availableWidth / imagesx($source), 1.0);
            $width = max(1, (int) floor(imagesx($source) * $scale));
            $height = max(1, (int) floor(imagesy($source) * $scale));
            if ($y + $height + 120 > imagesy($canvas)) {
                imagedestroy($source);
                imagedestroy($canvas);
                throw new \RuntimeException('The rendered receipt is too tall for the printer page.');
            }
            $x = (int) floor((self::WIDTH - $width) / 2);
            imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $height, imagesx($source), imagesy($source));
            imagedestroy($source);
            $y += $height + 28;
        }

        imageline($canvas, self::MARGIN, $y, self::WIDTH - self::MARGIN, $y, $black);
        $y += 30;
        if ($hasTrueType) {
            $this->centerText($canvas, 'THANKS FOR STOPPING BY', $bold, 14, $y, $black);
            $y += 28;
            $this->centerText($canvas, 'PING.PHILMCDONNELL.COM', $bold, 12, $y, $black);
            $y += 38;
        } else {
            imagestring($canvas, 4, 150, $y, 'THANKS FOR STOPPING BY', $black);
            $y += 24;
            imagestring($canvas, 3, 160, $y, 'PING.PHILMCDONNELL.COM', $black);
            $y += 36;
        }

        $receipt = imagecrop($canvas, ['x' => 0, 'y' => 0, 'width' => self::WIDTH, 'height' => min($y, imagesy($canvas))]);
        imagedestroy($canvas);
        if ($receipt === false || !imagepng($receipt, $outputPath, 7)) {
            if ($receipt !== false) {
                imagedestroy($receipt);
            }
            throw new \RuntimeException('Unable to render the native Windows receipt.');
        }
        imagedestroy($receipt);
    }

    private function drawLine($canvas, string $text, string $font, int $size, int $y, int $color, bool $trueType, int $spacing = 22): int
    {
        if ($trueType) {
            imagettftext($canvas, $size, 0, self::MARGIN, $y, $color, $font, $text);
        } else {
            imagestring($canvas, 3, self::MARGIN, $y - 13, $text, $color);
        }
        return $y + $spacing;
    }

    private function centerText($canvas, string $text, string $font, int $size, int $y, int $color): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        imagettftext($canvas, $size, 0, (int) ((self::WIDTH - $width) / 2), $y, $color, $font, $text);
    }

    private function wrap(string $message, int $columns): array
    {
        $lines = [];
        foreach (explode("\n", $message) as $paragraph) {
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }
            $wrapped = wordwrap($paragraph, $columns, "\n", true);
            array_push($lines, ...explode("\n", $wrapped));
        }
        return $lines;
    }
}
