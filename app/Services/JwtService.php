<?php

namespace App\Services;

use RuntimeException;

class JwtService
{
    public function encode(array $payload): string
    {
        $header = $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload['iat'] = time();
        $payload['exp'] = time() + 86400;
        $body = $this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64Url(hash_hmac('sha256', "$header.$body", $this->secret(), true));
        return "$header.$body.$signature";
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token');
        }

        [$header, $body, $signature] = $parts;
        $expected = $this->base64Url(hash_hmac('sha256', "$header.$body", $this->secret(), true));
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid signature');
        }

        $payload = json_decode($this->base64UrlDecode($body), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            throw new RuntimeException('Expired token');
        }

        return $payload;
    }

    private function secret(): string
    {
        return config('app.jwt_secret') ?: config('app.key') ?: 'library-api-development-secret';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}
