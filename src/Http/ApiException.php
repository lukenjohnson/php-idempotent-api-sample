<?php

declare(strict_types=1);

namespace Example\Http;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message
    ) {
        parent::__construct($message);
    }

    /** @return array{error: array{code: string, message: string}} */
    public function envelope(): array
    {
        return ['error' => ['code' => $this->errorCode, 'message' => $this->getMessage()]];
    }

    public static function unauthorized(): self
    {
        return new self(401, 'unauthorized', 'A valid API key is required.');
    }

    public static function forbiddenScope(): self
    {
        return new self(403, 'forbidden_scope', 'The API key lacks the required scope.');
    }

    public static function invalidRequest(string $message): self
    {
        return new self(422, 'validation_error', $message);
    }

    public static function notFound(string $resource): self
    {
        return new self(404, $resource . '_not_found', ucfirst($resource) . ' was not found.');
    }

    public static function idempotencyConflict(): self
    {
        return new self(409, 'idempotency_conflict', 'This idempotency key was used for a different request.');
    }

    public static function requestInProgress(): self
    {
        return new self(409, 'request_in_progress', 'A request with this idempotency key is still processing.');
    }
}
