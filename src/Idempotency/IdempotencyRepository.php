<?php

declare(strict_types=1);

namespace Example\Idempotency;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;

final class IdempotencyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $organizationId, int $apiKeyId, string $key): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM idempotency_records
             WHERE organization_id = :organization_id
               AND api_key_id = :api_key_id
               AND idempotency_key = :idempotency_key'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'api_key_id' => $apiKeyId,
            'idempotency_key' => $key,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function claim(int $organizationId, int $apiKeyId, string $key, string $requestHash): ?array
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO idempotency_records
                 (organization_id, api_key_id, idempotency_key, request_hash, status, created_at)
                 VALUES (:organization_id, :api_key_id, :idempotency_key, :request_hash, :status, :created_at)'
            );
            $statement->execute([
                'organization_id' => $organizationId,
                'api_key_id' => $apiKeyId,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'created_at' => self::now(),
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            return null;
        }

        return $this->find($organizationId, $apiKeyId, $key);
    }

    /** @param array<string, mixed> $response */
    public function complete(int $recordId, int $resourceId, int $responseStatus, array $response): void
    {
        $this->pdo->prepare(
            'UPDATE idempotency_records
             SET status = :status,
                 resource_id = :resource_id,
                 response_status = :response_status,
                 response_json = :response_json,
                 completed_at = :completed_at
             WHERE id = :id'
        )->execute([
            'status' => 'completed',
            'resource_id' => $resourceId,
            'response_status' => $responseStatus,
            'response_json' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'completed_at' => self::now(),
            'id' => $recordId,
        ]);
    }

    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
