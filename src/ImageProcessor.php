<?php

declare(strict_types=1);

namespace Ping;

final class ImageProcessor
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly string $uploadPath,
        private readonly int $maxBytes,
        private readonly int $maxPixels,
        private readonly int $targetWidth,
        private readonly int $maxHeight = 1200,
    ) {
    }

    public function hasUpload(?array $file): bool
    {
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function process(?array $file): ?string
    {
        if (!$this->hasUpload($file)) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException('The image upload did not complete. Please try again.');
        }
        if (!isset($file['tmp_name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException('The uploaded image could not be verified.');
        }
        if ((int) $file['size'] > $this->maxBytes) {
            throw new ValidationException('Images may be no larger than 5 MB.');
        }

        return $this->processPath($file['tmp_name'], (int) $file['size']);
    }

    public function processPath(string $path, ?int $knownSize = null): string
    {
        $size = $knownSize ?? filesize($path);
        if ($size === false || $size > $this->maxBytes) {
            throw new ValidationException('Images may be no larger than 5 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException('Attach a JPEG, PNG, or WebP image.');
        }

        $dimensions = @getimagesize($path);
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw new ValidationException('The uploaded file is not a readable image.');
        }
        if ($dimensions[0] * $dimensions[1] > $this->maxPixels) {
            throw new ValidationException('The image dimensions are too large to process safely.');
        }

        $bytes = file_get_contents($path);
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new ValidationException('The uploaded image could not be decoded.');
        }

        $scale = min($this->targetWidth / imagesx($source), $this->maxHeight / imagesy($source), 1.0);
        $width = max(1, (int) floor(imagesx($source) * $scale));
        $height = max(1, (int) floor(imagesy($source) * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imagedestroy($source);

        imagefilter($canvas, IMG_FILTER_GRAYSCALE);
        imagefilter($canvas, IMG_FILTER_CONTRAST, -12);

        if (!is_dir($this->uploadPath) && !mkdir($this->uploadPath, 0770, true) && !is_dir($this->uploadPath)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Unable to prepare private image storage.');
        }
        $path = $this->uploadPath . '/' . bin2hex(random_bytes(16)) . '.png';
        if (!imagepng($canvas, $path, 7)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Unable to store the processed image.');
        }
        imagedestroy($canvas);

        return $path;
    }
}
