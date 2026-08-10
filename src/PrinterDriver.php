<?php

declare(strict_types=1);

namespace Ping;

interface PrinterDriver
{
    public function print(string $transaction, string $message, ?string $imagePath): void;
}
