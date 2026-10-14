<?php

declare(strict_types=1);

namespace App\Services\Social\Meta;

/**
 * Meta's `signed_request`: `<signature>.<payload>`, both base64url without
 * padding. The signature is HMAC-SHA256 of the encoded payload keyed with the
 * app secret, and the payload declares `algorithm: HMAC-SHA256`.
 *
 * @see https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback
 * @see https://developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow#deauth-callback
 */
class SignedRequest
{
    private const ALGORITHM = 'HMAC-SHA256';

    /**
     * The payload when the request was signed with `$secret`, null otherwise.
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $signedRequest, string $secret): ?array
    {
        if ($secret === '' || substr_count($signedRequest, '.') !== 1) {
            return null;
        }

        [$encodedSignature, $encodedPayload] = explode('.', $signedRequest, 2);

        $signature = self::decode($encodedSignature);
        $json = self::decode($encodedPayload);

        if ($signature === null || $json === null) {
            return null;
        }

        if (! hash_equals(hash_hmac('sha256', $encodedPayload, $secret, true), $signature)) {
            return null;
        }

        $payload = json_decode($json, true);

        if (! is_array($payload) || strtoupper((string) data_get($payload, 'algorithm')) !== self::ALGORITHM) {
            return null;
        }

        return $payload;
    }

    private static function decode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $value) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
