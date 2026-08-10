<?php

declare(strict_types=1);

namespace Ping;

final class PingService
{
    public function __construct(
        private readonly MessageValidator $validator,
        private readonly ImageProcessor $images,
        private readonly ReceiptRepository $receipts,
        private readonly RateLimiter $rateLimiter,
        private readonly PrinterDriver $printer,
    ) {
    }

    public function send(?string $message, ?array $image, string $clientHash): string
    {
        if (!$this->rateLimiter->consume($clientHash)) {
            throw new ValidationException('Too many PINGs were sent recently. Please wait a minute and try again.');
        }

        $hasImage = $this->images->hasUpload($image);
        $message = $this->validator->validate($message, $hasImage);
        $imagePath = $this->images->process($image);
        $transaction = $this->transactionNumber();
        $id = $this->receipts->create($transaction, $message, $imagePath, $clientHash);

        try {
            $this->printer->print($transaction, $message, $imagePath);
            $this->receipts->markPrinted($id);
            if ($imagePath !== null && is_file($imagePath)) {
                @unlink($imagePath);
            }
        } catch (\Throwable $exception) {
            $this->receipts->markFailed($id, $exception->getMessage());
            throw new DeliveryException('Your PING was saved, but the printer is temporarily unavailable. Phil can retry it.', 0, $exception);
        }

        return $transaction;
    }

    private function transactionNumber(): string
    {
        return str_pad((string) random_int(0, 99_999), 5, '0', STR_PAD_LEFT);
    }
}
