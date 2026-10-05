<?php

declare(strict_types=1);

use Example\Auth\ApiKeyService;
use Example\Auth\BearerToken;
use Example\Database\Connection;
use Example\Http\ApiException;
use Example\Invoice\CreateDraftInvoice;

require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json');

$pdo = Connection::sqlite(dirname(__DIR__) . '/var/sample.sqlite');
$organizationId = 1; // A real application resolves this from the authenticated host or route.

try {
    $rawKey = BearerToken::parse($_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if ($rawKey === null) {
        throw ApiException::unauthorized();
    }

    $apiKey = (new ApiKeyService($pdo))->authenticate(
        $organizationId,
        $rawKey,
        ApiKeyService::CREATE_INVOICES
    );
    $result = (new CreateDraftInvoice($pdo))->handle(
        $organizationId,
        $apiKey,
        $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '',
        file_get_contents('php://input') ?: ''
    );
    http_response_code($result['status']);
    echo json_encode($result['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (ApiException $exception) {
    http_response_code($exception->httpStatus);
    echo json_encode($exception->envelope(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}
