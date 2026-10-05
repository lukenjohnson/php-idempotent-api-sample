<?php

declare(strict_types=1);

namespace Example\Invoice;

use Example\Http\ApiException;
use JsonException;

final class InvoiceRequest
{
    /** @return array{client_public_id: string, title: string, lines: list<array{preset_public_id: string, quantity: int}>} */
    public static function fromJson(string $json): array
    {
        try {
            $input = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ApiException::invalidRequest('The request body must be valid JSON.');
        }
        if (!is_array($input)) {
            throw ApiException::invalidRequest('The request body must be a JSON object.');
        }

        $allowed = ['client_public_id', 'title', 'lines'];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw ApiException::invalidRequest('Unknown request fields are not accepted.');
        }

        $clientId = trim((string) ($input['client_public_id'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $lines = $input['lines'] ?? null;
        if ($clientId === '' || $title === '' || !is_array($lines) || $lines === []) {
            throw ApiException::invalidRequest('client_public_id, title, and at least one line are required.');
        }

        $normalizedLines = [];
        foreach ($lines as $line) {
            if (!is_array($line) || array_diff(array_keys($line), ['preset_public_id', 'quantity']) !== []) {
                throw ApiException::invalidRequest('Each line accepts preset_public_id and quantity only.');
            }
            $presetId = trim((string) ($line['preset_public_id'] ?? ''));
            $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($presetId === '' || $quantity === false || $quantity < 1 || $quantity > 10000) {
                throw ApiException::invalidRequest('Each line requires a preset ID and a quantity from 1 to 10000.');
            }
            $normalizedLines[] = ['preset_public_id' => $presetId, 'quantity' => $quantity];
        }

        return ['client_public_id' => $clientId, 'title' => $title, 'lines' => $normalizedLines];
    }

    public static function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 128 || preg_match('/^[\x21-\x7E]+$/', $key) !== 1) {
            throw ApiException::invalidRequest('Idempotency-Key must contain 1–128 visible ASCII characters.');
        }

        return $key;
    }

    /** @param array<string, mixed> $request */
    public static function canonicalHash(array $request): string
    {
        return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
