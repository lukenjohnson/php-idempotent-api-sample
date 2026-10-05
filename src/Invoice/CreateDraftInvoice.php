<?php

declare(strict_types=1);

namespace Example\Invoice;

use DateTimeImmutable;
use DateTimeZone;
use Example\Database\Connection;
use Example\Http\ApiException;
use Example\Idempotency\IdempotencyRepository;
use PDO;

final class CreateDraftInvoice
{
    private readonly IdempotencyRepository $idempotency;

    public function __construct(private readonly PDO $pdo)
    {
        $this->idempotency = new IdempotencyRepository($pdo);
    }

    /**
     * @param array<string, mixed> $apiKey
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(int $organizationId, array $apiKey, string $idempotencyKey, string $json): array
    {
        $request = InvoiceRequest::fromJson($json);
        $key = InvoiceRequest::normalizeIdempotencyKey($idempotencyKey);
        $requestHash = InvoiceRequest::canonicalHash($request);

        return Connection::immediateTransaction($this->pdo, function () use (
            $organizationId,
            $apiKey,
            $key,
            $requestHash,
            $request
        ): array {
            $apiKeyId = (int) $apiKey['id'];
            $existing = $this->idempotency->find($organizationId, $apiKeyId, $key);
            if ($existing !== null) {
                return $this->replayOrReject($existing, $requestHash);
            }

            $claim = $this->idempotency->claim($organizationId, $apiKeyId, $key, $requestHash);
            if ($claim === null) {
                $existing = $this->idempotency->find($organizationId, $apiKeyId, $key);
                if ($existing === null) {
                    throw ApiException::requestInProgress();
                }

                return $this->replayOrReject($existing, $requestHash);
            }

            $client = $this->findClient($organizationId, $request['client_public_id']);
            $totalCents = 0;
            $resolvedLines = [];
            foreach ($request['lines'] as $line) {
                $preset = $this->findPreset($organizationId, $line['preset_public_id']);
                $subtotal = (int) $preset['unit_price_cents'] * $line['quantity'];
                $totalCents += $subtotal;
                $resolvedLines[] = ['preset' => $preset, 'quantity' => $line['quantity'], 'subtotal' => $subtotal];
            }

            $createdAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
            $statement = $this->pdo->prepare(
                'INSERT INTO invoices
                 (organization_id, client_id, title, status, total_cents, created_at)
                 VALUES (:organization_id, :client_id, :title, :status, :total_cents, :created_at)'
            );
            $statement->execute([
                'organization_id' => $organizationId,
                'client_id' => $client['id'],
                'title' => $request['title'],
                'status' => 'draft',
                'total_cents' => $totalCents,
                'created_at' => $createdAt,
            ]);
            $invoiceId = (int) $this->pdo->lastInsertId();

            $lineStatement = $this->pdo->prepare(
                'INSERT INTO invoice_lines
                 (invoice_id, preset_id, title, quantity, unit_price_cents, subtotal_cents)
                 VALUES (:invoice_id, :preset_id, :title, :quantity, :unit_price_cents, :subtotal_cents)'
            );
            foreach ($resolvedLines as $line) {
                $lineStatement->execute([
                    'invoice_id' => $invoiceId,
                    'preset_id' => $line['preset']['id'],
                    'title' => $line['preset']['title'],
                    'quantity' => $line['quantity'],
                    'unit_price_cents' => $line['preset']['unit_price_cents'],
                    'subtotal_cents' => $line['subtotal'],
                ]);
            }

            $body = ['invoice' => [
                'id' => $invoiceId,
                'status' => 'draft',
                'title' => $request['title'],
                'total' => number_format($totalCents / 100, 2, '.', ''),
            ]];
            $this->idempotency->complete((int) $claim['id'], $invoiceId, 201, $body);

            return ['status' => 201, 'body' => $body];
        });
    }

    /** @return array<string, mixed> */
    private function findClient(int $organizationId, string $publicId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM clients
             WHERE organization_id = :organization_id AND public_id = :public_id AND active = 1'
        );
        $statement->execute(['organization_id' => $organizationId, 'public_id' => $publicId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw ApiException::notFound('client');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function findPreset(int $organizationId, string $publicId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM invoice_presets
             WHERE organization_id = :organization_id AND public_id = :public_id AND active = 1'
        );
        $statement->execute(['organization_id' => $organizationId, 'public_id' => $publicId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw ApiException::notFound('preset');
        }

        return $row;
    }

    /** @param array<string, mixed> $record */
    private function replayOrReject(array $record, string $requestHash): array
    {
        if (!hash_equals((string) $record['request_hash'], $requestHash)) {
            throw ApiException::idempotencyConflict();
        }
        if ($record['status'] !== 'completed' || !is_string($record['response_json'])) {
            throw ApiException::requestInProgress();
        }

        return [
            'status' => 200,
            'body' => json_decode($record['response_json'], true, flags: JSON_THROW_ON_ERROR),
        ];
    }
}
