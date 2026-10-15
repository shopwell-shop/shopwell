<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Encrypts the secrets of the payment gateway at rest: the app secret and the channel credentials
 * (merchant ids, private keys, api keys) are never stored in plain text.
 *
 * The encryption key is derived from the application secret, it is never stored in the database.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentCredentialCipher
{
    /**
     * Marker of an encrypted payload inside a json field.
     */
    final public const ENCRYPTED_KEY = '_encrypted';

    public function __construct(private readonly string $secret)
    {
    }

    public function encryptValue(string $plain): string
    {
        $key = $this->key();
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public function decryptValue(string $payload): string
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false || \strlen($decoded) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw PaymentException::missingCredentials('cipher', 'payload');
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());

        if ($plain === false) {
            throw PaymentException::missingCredentials('cipher', 'payload');
        }

        return $plain;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function encrypt(array $data): array
    {
        if ($data === []) {
            return [];
        }

        return [self::ENCRYPTED_KEY => $this->encryptValue((string) json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES))];
    }

    /**
     * @param array<string, mixed>|null $data
     *
     * @return array<string, mixed>
     */
    public function decrypt(?array $data): array
    {
        if ($data === null || $data === []) {
            return [];
        }

        if (!isset($data[self::ENCRYPTED_KEY]) || !\is_string($data[self::ENCRYPTED_KEY])) {
            throw PaymentException::missingCredentials('cipher', self::ENCRYPTED_KEY);
        }

        $plain = $this->decryptValue($data[self::ENCRYPTED_KEY]);
        $decoded = json_decode($plain, true);

        if (!\is_array($decoded)) {
            throw PaymentException::missingCredentials('cipher', 'payload');
        }

        return $decoded;
    }

    private function key(): string
    {
        return sodium_crypto_generichash($this->secret, 'shopwell.payment', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
