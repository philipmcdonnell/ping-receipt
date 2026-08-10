<?php

declare(strict_types=1);

namespace Ping;

final class MessageValidator
{
    public function __construct(private readonly int $maxLength = 1024)
    {
    }

    public function validate(?string $message, bool $hasImage): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", trim((string) $message));
        $message = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{2026}"],
            ["'", "'", '"', '"', '-', '-', '...'],
            $message,
        );

        if ($message === '' && !$hasImage) {
            throw new ValidationException('Write a message or attach an image before sending your PING.');
        }
        if (mb_strlen($message, 'UTF-8') > $this->maxLength) {
            throw new ValidationException("Messages may contain at most {$this->maxLength} characters.");
        }
        if ($message !== '' && preg_match('/\A[\x20-\x7E\n\t]*\z/', $message) !== 1) {
            throw new ValidationException('Use basic printable text only. Emojis and special symbols are not supported yet.');
        }

        return $message;
    }
}
