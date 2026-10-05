<?php

declare(strict_types=1);

namespace Example\Tests;

use Example\Auth\ApiKeyService;
use Example\Auth\BearerToken;
use Example\Http\ApiException;

final class ApiKeyServiceTest extends DatabaseTestCase
{
    public function testBearerTokenParsingIsStrict(): void
    {
        self::assertSame($this->rawApiKey, BearerToken::parse('Bearer ' . $this->rawApiKey));
        self::assertNull(BearerToken::parse($this->rawApiKey));
        self::assertNull(BearerToken::parse('Basic ' . $this->rawApiKey));
    }

    public function testKeyIsStoredAsAHashAndCanAuthenticate(): void
    {
        self::assertNotSame($this->rawApiKey, $this->apiKey['key_hash']);
        self::assertSame(hash('sha256', $this->rawApiKey), $this->apiKey['key_hash']);

        $authenticated = (new ApiKeyService($this->pdo))->authenticate(
            $this->organizationId,
            $this->rawApiKey,
            ApiKeyService::CREATE_INVOICES
        );
        self::assertSame($this->apiKey['id'], $authenticated['id']);
    }

    public function testRevokedKeyIsRejected(): void
    {
        $service = new ApiKeyService($this->pdo);
        $service->revoke($this->organizationId, (int) $this->apiKey['id']);

        $this->expectException(ApiException::class);
        $service->authenticate($this->organizationId, $this->rawApiKey, ApiKeyService::CREATE_INVOICES);
    }
}
