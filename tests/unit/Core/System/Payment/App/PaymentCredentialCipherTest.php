<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCredentialCipher::class)]
class PaymentCredentialCipherTest extends TestCase
{
    public function testEncryptValueRoundTripsThroughDecryptValue(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        $encrypted = $cipher->encryptValue('the merchant secret');

        static::assertNotSame('the merchant secret', $encrypted);
        static::assertSame('the merchant secret', $cipher->decryptValue($encrypted));
    }

    public function testEncryptValueRoundTripsAnEmptyString(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        static::assertSame('', $cipher->decryptValue($cipher->encryptValue('')));
    }

    public function testEncryptValueRoundTripsUnicode(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        static::assertSame('密钥/秘密', $cipher->decryptValue($cipher->encryptValue('密钥/秘密')));
    }

    public function testEncryptValueUsesAFreshNonce(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        static::assertNotSame($cipher->encryptValue('same input'), $cipher->encryptValue('same input'));
    }

    public function testDecryptValueRejectsAPayloadThatIsNotBase64(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        $this->expectExceptionObject(PaymentException::missingCredentials('cipher', 'payload'));

        $cipher->decryptValue('!!! not base64 !!!');
    }

    public function testDecryptValueRejectsAPayloadThatIsTooShortForANonce(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        $this->expectExceptionObject(PaymentException::missingCredentials('cipher', 'payload'));

        $cipher->decryptValue(base64_encode(random_bytes(10)));
    }

    public function testDecryptValueRejectsATamperedCiphertext(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        $payload = base64_decode($cipher->encryptValue('the merchant secret'), true);
        static::assertIsString($payload);

        $payload[\strlen($payload) - 1] = $payload[\strlen($payload) - 1] === 'A' ? 'B' : 'A';

        $this->expectExceptionObject(PaymentException::missingCredentials('cipher', 'payload'));

        $cipher->decryptValue(base64_encode($payload));
    }

    public function testDecryptValueRejectsTheWrongSecret(): void
    {
        $encrypted = (new PaymentCredentialCipher('first-secret'))->encryptValue('the merchant secret');

        $this->expectExceptionObject(PaymentException::missingCredentials('cipher', 'payload'));

        (new PaymentCredentialCipher('second-secret'))->decryptValue($encrypted);
    }

    public function testEncryptAnEmptyArrayAnswersAnEmptyArray(): void
    {
        static::assertSame([], (new PaymentCredentialCipher('application-secret'))->encrypt([]));
    }

    public function testEncryptWrapsTheJsonPayloadUnderTheMarker(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        $encrypted = $cipher->encrypt(['secret_key' => 'sk_test_123', 'nested' => ['a' => 1]]);

        static::assertSame([PaymentCredentialCipher::ENCRYPTED_KEY], array_keys($encrypted));
        static::assertIsString($encrypted[PaymentCredentialCipher::ENCRYPTED_KEY]);
        static::assertSame(
            ['secret_key' => 'sk_test_123', 'nested' => ['a' => 1]],
            $cipher->decrypt($encrypted)
        );
    }

    public function testDecryptTreatsNullAndEmptyArrayAsEmpty(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        static::assertSame([], $cipher->decrypt(null));
        static::assertSame([], $cipher->decrypt([]));
    }

    public function testDecryptRejectsAPayloadWithoutTheMarker(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        $this->expectExceptionObject(PaymentException::missingCredentials('cipher', PaymentCredentialCipher::ENCRYPTED_KEY));

        $cipher->decrypt(['secret_key' => 'plain']);
    }

    public function testDecryptRejectsAMarkerThatIsNotAString(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        $this->expectExceptionObject(PaymentException::missingCredentials('cipher', PaymentCredentialCipher::ENCRYPTED_KEY));

        $cipher->decrypt([PaymentCredentialCipher::ENCRYPTED_KEY => 42]);
    }

    public function testDecryptRejectsAPayloadWhoseJsonIsNotAnArray(): void
    {
        $cipher = new PaymentCredentialCipher('application-secret');

        $payload = [PaymentCredentialCipher::ENCRYPTED_KEY => $cipher->encryptValue('"just a string"')];

        $this->expectExceptionObject(PaymentException::missingCredentials('cipher', 'payload'));

        $cipher->decrypt($payload);
    }
}
