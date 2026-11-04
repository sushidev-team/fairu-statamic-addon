<?php

namespace Sushidev\Fairu\Support;

use InvalidArgumentException;

/**
 * Signs visible-watermark bypass URLs (`wm=0&wms=<sig>`).
 *
 * Implements the normative contract from the Fairu visible watermark plan (§5.5):
 *
 *   canonical = "v1\n" + file_id + "\n" + canonical_query
 *   wms       = first 22 chars of base64url_nopad(HMAC-SHA256(raw_key, canonical))
 *
 * canonical_query contains every query parameter except "wms", drops empty values,
 * sorts keys by byte order and RFC 3986-encodes key and value. The HMAC key is the
 * 32 raw bytes behind the tenant's display key ("fwm_" + base64url_nopad(raw_key)).
 *
 * Kept self-contained on purpose: the addon does not depend on fairu-sdk.
 */
final class WatermarkSignature
{
    public const KEY_PREFIX = 'fwm_';

    public const SIGNATURE_LENGTH = 22;

    /**
     * @param  array<string, scalar|null>  $query  final query parameters (as the proxy will parse them)
     *
     * @throws InvalidArgumentException when the display key is malformed
     */
    public static function sign(string $displayKey, string $fileId, array $query): string
    {
        $mac = hash_hmac('sha256', self::canonical($fileId, $query), self::rawKey($displayKey), true);

        return substr(self::base64UrlEncode($mac), 0, self::SIGNATURE_LENGTH);
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public static function canonical(string $fileId, array $query): string
    {
        return "v1\n".$fileId."\n".self::canonicalQuery($query);
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public static function canonicalQuery(array $query): string
    {
        $pairs = [];

        foreach ($query as $key => $value) {
            $key = (string) $key;

            if ($key === 'wms' || $value === null) {
                continue;
            }

            $value = self::stringValue($value);

            if ($value === '') {
                continue;
            }

            $pairs[$key] = $value;
        }

        ksort($pairs, SORT_STRING);

        $encoded = [];
        foreach ($pairs as $key => $value) {
            $encoded[] = rawurlencode((string) $key).'='.rawurlencode($value);
        }

        return implode('&', $encoded);
    }

    /**
     * Decode a display key ("fwm_" + base64url_nopad(32 bytes)) to the raw HMAC key.
     *
     * @throws InvalidArgumentException
     */
    public static function rawKey(string $displayKey): string
    {
        $displayKey = trim($displayKey);

        if (! str_starts_with($displayKey, self::KEY_PREFIX)) {
            throw new InvalidArgumentException('Watermark key must start with "'.self::KEY_PREFIX.'".');
        }

        $encoded = substr($displayKey, strlen(self::KEY_PREFIX));

        if ($encoded === '' || preg_match('/^[A-Za-z0-9_-]+$/', $encoded) !== 1) {
            throw new InvalidArgumentException('Watermark key is not valid base64url.');
        }

        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($raw === false || strlen($raw) !== 32) {
            throw new InvalidArgumentException('Watermark key must decode to 32 bytes.');
        }

        return $raw;
    }

    /**
     * Split a query string the way the proxy parses it (application/x-www-form-urlencoded),
     * so the signature covers exactly what the proxy will see.
     *
     * @return array<string, string>
     */
    public static function parseQuery(string $queryString): array
    {
        $params = [];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $params[urldecode($key)] = urldecode($value);
        }

        return $params;
    }

    private static function stringValue(mixed $value): string
    {
        return match (true) {
            $value === true => '1',
            $value === false => '0',
            default => (string) $value,
        };
    }

    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
