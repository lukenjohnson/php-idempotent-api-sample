<?php

declare(strict_types=1);

namespace Example\Tests;

use Example\Http\ApiException;
use Example\Invoice\CreateDraftInvoice;

final class CreateDraftInvoiceTest extends DatabaseTestCase
{
    public function testCreatesOneTransactionalDraft(): void
    {
        $result = (new CreateDraftInvoice($this->pdo))->handle(
            $this->organizationId,
            $this->apiKey,
            'invoice:example:2026-10',
            $this->request()
        );

        self::assertSame(201, $result['status']);
        self::assertSame('300.00', $result['body']['invoice']['total']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM invoice_lines')->fetchColumn());
    }

    public function testSameKeyAndLogicalRequestReplaysOriginalResponse(): void
    {
        $service = new CreateDraftInvoice($this->pdo);
        $body = $this->request();
        $first = $service->handle($this->organizationId, $this->apiKey, 'stable-key', $body);
        $pretty = json_encode(json_decode($body, true, flags: JSON_THROW_ON_ERROR), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $replay = $service->handle($this->organizationId, $this->apiKey, 'stable-key', $pretty);

        self::assertSame(201, $first['status']);
        self::assertSame(200, $replay['status']);
        self::assertSame($first['body'], $replay['body']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn());
    }

    public function testSameKeyWithDifferentRequestReturnsConflict(): void
    {
        $service = new CreateDraftInvoice($this->pdo);
        $service->handle($this->organizationId, $this->apiKey, 'stable-key', $this->request());

        try {
            $service->handle($this->organizationId, $this->apiKey, 'stable-key', $this->request('Changed title'));
            self::fail('Expected an idempotency conflict.');
        } catch (ApiException $exception) {
            self::assertSame(409, $exception->httpStatus);
            self::assertSame('idempotency_conflict', $exception->errorCode);
        }
    }

    public function testCrossOrganizationIdentifiersAreNotVisible(): void
    {
        $body = json_encode([
            'client_public_id' => 'cli_other',
            'title' => 'Should not exist',
            'lines' => [['preset_public_id' => 'pst_other', 'quantity' => 1]],
        ], JSON_THROW_ON_ERROR);

        try {
            (new CreateDraftInvoice($this->pdo))->handle(
                $this->organizationId,
                $this->apiKey,
                'cross-org',
                $body
            );
            self::fail('Expected the cross-organization client lookup to fail.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->httpStatus);
            self::assertSame('client_not_found', $exception->errorCode);
        }
    }
}
