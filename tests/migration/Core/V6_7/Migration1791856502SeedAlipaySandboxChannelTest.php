<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Migration\V6_7\Migration1791856502SeedAlipaySandboxChannel;

/**
 * The seeded sandbox credentials have to be a complete, matching key pair: the first version of the
 * migration shipped truncated keys, which no rsa operation of the alipay sdk could work with.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1791856502SeedAlipaySandboxChannel::class)]
class Migration1791856502SeedAlipaySandboxChannelTest extends TestCase
{
    public function testTheSeededKeyPairParsesAndMatches(): void
    {
        $private = openssl_pkey_get_private(self::wrappedPrivateKey());
        static::assertNotFalse($private);

        $details = openssl_pkey_get_details($private);
        static::assertIsArray($details);
        static::assertSame(2048, $details['bits']);
        static::assertSame(\OPENSSL_KEYTYPE_RSA, $details['type']);

        $public = openssl_pkey_get_public(self::wrappedPublicKey());
        static::assertNotFalse($public);

        // the private key signs what the seeded alipay public key verifies: they belong together
        $signature = '';
        static::assertTrue(openssl_sign('sandbox credentials', $signature, $private, \OPENSSL_ALGO_SHA256));
        static::assertSame(1, openssl_verify('sandbox credentials', $signature, $public, \OPENSSL_ALGO_SHA256));
    }

    public function testTheSeededKeysAreNoTruncatedFragments(): void
    {
        // the pkcs#8 body of a 2048 bit key is around 1600 characters, the spki body around 390; the
        // truncated first version of the migration had around 560 and 50
        static::assertGreaterThan(1000, \strlen(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_PRIVATE_KEY));
        static::assertGreaterThan(300, \strlen(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_ALIPAY_PUBLIC_KEY));
    }

    /**
     * The pem envelope `AlipayApi::wrapPem()` builds around the bare key bodies of the migration.
     */
    private static function wrappedPrivateKey(): string
    {
        return "-----BEGIN RSA PRIVATE KEY-----\n"
            . wordwrap(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_PRIVATE_KEY, 64, "\n", true)
            . "\n-----END RSA PRIVATE KEY-----";
    }

    private static function wrappedPublicKey(): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . wordwrap(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_ALIPAY_PUBLIC_KEY, 64, "\n", true)
            . "\n-----END PUBLIC KEY-----";
    }
}
