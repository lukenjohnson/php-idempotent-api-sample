<?php

declare(strict_types=1);

namespace Example\Database;

use PDO;
use Throwable;

final class Connection
{
    public static function sqlite(string $path = ':memory:'): PDO
    {
        $pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    public static function migrate(PDO $pdo): void
    {
        $schema = file_get_contents(dirname(__DIR__, 2) . '/schema.sql');
        if ($schema === false) {
            throw new \RuntimeException('Unable to read schema.sql.');
        }
        $pdo->exec($schema);
    }

    /**
     * SQLite's BEGIN IMMEDIATE takes the write lock before checking the
     * idempotency record, closing the race between claim and resource write.
     */
    public static function immediateTransaction(PDO $pdo, callable $operation): mixed
    {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $operation();
            $pdo->exec('COMMIT');

            return $result;
        } catch (Throwable $exception) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable) {
                // Preserve the original exception if SQLite already ended the transaction.
            }
            throw $exception;
        }
    }
}
