<?php

declare(strict_types=1);

namespace Ping;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the database directory.');
        }

        $pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrate($pdo);

        return $pdo;
    }

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS pings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_number TEXT NOT NULL,
                message TEXT,
                image_path TEXT,
                status TEXT NOT NULL CHECK (status IN ('pending', 'printed', 'failed')),
                error TEXT,
                client_hash TEXT NOT NULL,
                created_at TEXT NOT NULL,
                printed_at TEXT,
                updated_at TEXT NOT NULL
            )
        SQL);
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS rate_limits (
                client_hash TEXT NOT NULL,
                window_start INTEGER NOT NULL,
                request_count INTEGER NOT NULL,
                PRIMARY KEY (client_hash, window_start)
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pings_status ON pings(status, created_at)');
    }
}
