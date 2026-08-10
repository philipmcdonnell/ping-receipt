<?php

declare(strict_types=1);

namespace Ping;

use PDO;

final class ReceiptRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $transaction, string $message, ?string $imagePath, string $clientHash): int
    {
        $now = gmdate('c');
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO pings (
                transaction_number, message, image_path, status, client_hash, created_at, updated_at
            ) VALUES (
                :transaction, :message, :image_path, 'pending', :client_hash, :created_at, :updated_at
            )
        SQL);
        $statement->execute([
            'transaction' => $transaction,
            'message' => $message !== '' ? $message : null,
            'image_path' => $imagePath,
            'client_hash' => $clientHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function markPrinted(int $id): void
    {
        $now = gmdate('c');
        $this->pdo->prepare(<<<'SQL'
            UPDATE pings SET status = 'printed', error = NULL, printed_at = :now, updated_at = :now WHERE id = :id
        SQL)->execute(['id' => $id, 'now' => $now]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->pdo->prepare(<<<'SQL'
            UPDATE pings SET status = 'failed', error = :error, updated_at = :now WHERE id = :id
        SQL)->execute(['id' => $id, 'error' => mb_substr($error, 0, 500), 'now' => gmdate('c')]);
    }

    public function findFailed(): array
    {
        return $this->pdo->query("SELECT * FROM pings WHERE status = 'failed' ORDER BY created_at")->fetchAll();
    }
}
