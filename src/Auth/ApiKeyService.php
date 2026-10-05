<?php

declare(strict_types=1);

namespace Example\Auth;

use DateTimeImmutable;
use DateTimeZone;
use Example\Http\ApiException;
use PDO;

final class ApiKeyService
{
    public const CREATE_INVOICES = 'invoices:create';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{id: int, raw_key: string} */
    public function create(int $organizationId, string $name, array $scopes = [self::CREATE_INVOICES]): array
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 80) {
            throw new \InvalidArgumentException('API key name must contain 1–80 characters.');
        }
        $rawKey = 'sample_' . bin2hex(random_bytes(32));
        $statement = $this->pdo->prepare(
            'INSERT INTO api_keys
             (organization_id, name, key_prefix, key_hash, scopes_json, created_at)
             VALUES (:organization_id, :name, :key_prefix, :key_hash, :scopes_json, :created_at)'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'name' => $name,
            'key_prefix' => substr($rawKey, 0, 14),
            'key_hash' => hash('sha256', $rawKey),
            'scopes_json' => json_encode(array_values(array_unique($scopes)), JSON_THROW_ON_ERROR),
            'created_at' => self::now(),
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'raw_key' => $rawKey];
    }

    /** @return array<string, mixed> */
    public function authenticate(int $organizationId, string $rawKey, string $requiredScope): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM api_keys
             WHERE organization_id = :organization_id
               AND key_hash = :key_hash
               AND revoked_at IS NULL'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'key_hash' => hash('sha256', $rawKey),
        ]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw ApiException::unauthorized();
        }

        $scopes = json_decode((string) $row['scopes_json'], true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($scopes) || !in_array($requiredScope, $scopes, true)) {
            throw ApiException::forbiddenScope();
        }

        $this->pdo->prepare('UPDATE api_keys SET last_used_at = :used_at WHERE id = :id')
            ->execute(['used_at' => self::now(), 'id' => $row['id']]);

        return $row;
    }

    public function revoke(int $organizationId, int $id): void
    {
        $this->pdo->prepare(
            'UPDATE api_keys SET revoked_at = :revoked_at
             WHERE id = :id AND organization_id = :organization_id'
        )->execute(['revoked_at' => self::now(), 'id' => $id, 'organization_id' => $organizationId]);
    }

    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
