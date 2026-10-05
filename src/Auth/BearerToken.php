<?php

declare(strict_types=1);

namespace Example\Auth;

final class BearerToken
{
    public static function parse(?string $authorizationHeader): ?string
    {
        if ($authorizationHeader === null) {
            return null;
        }
        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($authorizationHeader), $matches)) {
            return null;
        }

        $token = $matches[1];

        return str_starts_with($token, 'sample_') && strlen($token) === 71 ? $token : null;
    }
}
