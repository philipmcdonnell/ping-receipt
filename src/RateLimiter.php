<?php

declare(strict_types=1);

namespace Ping;

use PDO;

final class RateLimiter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $limit,
        private readonly int $windowSeconds,
    ) {
    }

    public function consume(string $clientHash, ?int $now = null): bool
    {
        $now ??= time();
        $window = intdiv($now, $this->windowSeconds) * $this->windowSeconds;

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM rate_limits WHERE window_start < :cutoff')
                ->execute(['cutoff' => $window - ($this->windowSeconds * 2)]);
            $this->pdo->prepare(<<<'SQL'
                INSERT INTO rate_limits (client_hash, window_start, request_count)
                VALUES (:client_hash, :window_start, 1)
                ON CONFLICT(client_hash, window_start)
                DO UPDATE SET request_count = request_count + 1
            SQL)->execute(['client_hash' => $clientHash, 'window_start' => $window]);
            $statement = $this->pdo->prepare(
                'SELECT request_count FROM rate_limits WHERE client_hash = :client_hash AND window_start = :window_start'
            );
            $statement->execute(['client_hash' => $clientHash, 'window_start' => $window]);
            $allowed = (int) $statement->fetchColumn() <= $this->limit;
            $this->pdo->commit();
            return $allowed;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
