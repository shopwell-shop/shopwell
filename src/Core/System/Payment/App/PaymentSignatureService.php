<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\Log\Package;

/**
 * Creates and verifies the signatures of the generic payment gateway.
 *
 * The caller sends its credentials as request parameters, never as a http header: `app_id`,
 * `timestamp`, `nonce` and `sign` are top level keys of the payload next to the business parameters.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentSignatureService
{
    final public const APP_ID = 'app_id';
    final public const TIMESTAMP = 'timestamp';
    final public const NONCE = 'nonce';
    final public const SIGN = 'sign';

    /**
     * @return list<string>
     */
    public static function authParameters(): array
    {
        return [self::APP_ID, self::TIMESTAMP, self::NONCE, self::SIGN];
    }

    /**
     * @param array<string, mixed> $params the complete payload, the auth parameters are stripped before signing
     */
    public function sign(
        string $secret,
        string $appId,
        int $timestamp,
        string $nonce,
        string $method,
        string $path,
        array $params
    ): string {
        $content = $this->buildSignContent($appId, $timestamp, $nonce, $method, $path, $params);

        return base64_encode(hash_hmac('sha256', $content, $secret, true));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function verify(
        string $secret,
        string $appId,
        int $timestamp,
        string $nonce,
        string $method,
        string $path,
        array $params,
        string $sign
    ): bool {
        return hash_equals(
            $this->sign($secret, $appId, $timestamp, $nonce, $method, $path, $params),
            $sign
        );
    }

    /**
     * Signs a raw payload, used for the notifications the platform sends to the app: the app verifies
     * the very same way and the body is signed as it is sent, so it is easy to reproduce on any stack.
     */
    public function signPayload(string $secret, string $payload): string
    {
        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }

    public function verifyPayload(string $secret, string $payload, string $signature): bool
    {
        return hash_equals($this->signPayload($secret, $payload), $signature);
    }

    /**
     * Sorts the business parameters and serializes them. The auth parameters and the signature itself
     * are not part of the signed content.
     *
     * @param array<string, mixed> $params
     */
    public function canonicalize(array $params): string
    {
        foreach (self::authParameters() as $authParameter) {
            unset($params[$authParameter]);
        }

        ksort($params);

        return (string) json_encode($params, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildSignContent(
        string $appId,
        int $timestamp,
        string $nonce,
        string $method,
        string $path,
        array $params
    ): string {
        return implode("\n", [
            $appId,
            (string) $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            $this->canonicalize($params),
        ]);
    }
}
