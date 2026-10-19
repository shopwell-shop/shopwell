<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferNotificationVerifier;
use Stripe\WebhookSignature;

/**
 * Stripe signs the raw body with the signing secret of the endpoint, and the payout number travels in
 * the metadata of the object the event carries.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeTransferNotificationVerifier::class)]
class StripeTransferNotificationVerifierTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    public function testProviderIsStripe(): void
    {
        static::assertSame(StripeApi::PROVIDER, (new StripeTransferNotificationVerifier())->provider());
    }

    public function testANotificationWithoutASignatureHeaderIsRejected(): void
    {
        static::assertFalse($this->verifier()->verify($this->credentials(), '{}', []));
    }

    public function testAnEmptyBodyWithoutASignatureIsRejected(): void
    {
        static::assertFalse($this->verifier()->verify($this->credentials(), '', []));
    }

    public function testASignatureOfAnotherSecretIsRejected(): void
    {
        $body = '{"id":"evt-1"}';
        $headers = [StripeNotificationExtractor::HEADER_SIGNATURE => WebhookSignature::generateSignatureHeader($body, 'whsec_other')];

        static::assertFalse($this->verifier()->verify($this->credentials(), $body, $headers));
    }

    public function testATamperedBodyIsRejected(): void
    {
        $headers = [StripeNotificationExtractor::HEADER_SIGNATURE => WebhookSignature::generateSignatureHeader('{"id":"evt-1"}', self::SECRET)];

        static::assertFalse($this->verifier()->verify($this->credentials(), '{"id":"evt-2"}', $headers));
    }

    public function testANotificationWithoutTheWebhookSecretThrows(): void
    {
        $headers = [StripeNotificationExtractor::HEADER_SIGNATURE => WebhookSignature::generateSignatureHeader('{}', self::SECRET)];

        $this->expectExceptionObject(PaymentException::missingCredentials(StripeApi::PROVIDER, StripeApi::WEBHOOK_SECRET));

        $this->verifier()->verify([], '{}', $headers);
    }

    public function testASignedNotificationIsAccepted(): void
    {
        $body = '{"id":"evt-1","type":"payout.paid"}';
        $headers = [StripeNotificationExtractor::HEADER_SIGNATURE => WebhookSignature::generateSignatureHeader($body, self::SECRET)];

        static::assertTrue($this->verifier()->verify($this->credentials(), $body, $headers));
    }

    public function testTheSignatureHeaderIsFoundWhateverItsCase(): void
    {
        $body = '{"id":"evt-1"}';
        $headers = ['Stripe_Signature' => [WebhookSignature::generateSignatureHeader($body, self::SECRET)]];

        static::assertTrue($this->verifier()->verify($this->credentials(), $body, $headers));
    }

    public function testThePayoutNumberIsTheOutNoOfTheMetadata(): void
    {
        $body = json_encode([
            'id' => 'evt-1',
            'data' => ['object' => ['id' => 'po_1', 'metadata' => ['out_no' => 'PO-1']]],
        ], \JSON_THROW_ON_ERROR);

        static::assertSame('PO-1', (new StripeTransferNotificationVerifier())->outNoOf([], $body, []));
    }

    public function testANotificationWithoutMetadataHasNoPayoutNumber(): void
    {
        static::assertNull((new StripeTransferNotificationVerifier())->outNoOf([], '{"id":"evt-1","data":{"object":{"id":"po_1"}}}', []));
    }

    public function testAnEmptyPayoutNumberIsNoPayoutNumber(): void
    {
        static::assertNull((new StripeTransferNotificationVerifier())->outNoOf(
            [],
            '{"data":{"object":{"metadata":{"out_no":""}}}}',
            []
        ));
    }

    public function testABrokenBodyHasNoPayoutNumber(): void
    {
        static::assertNull((new StripeTransferNotificationVerifier())->outNoOf([], 'not-json', []));
    }

    public function testStripeIsAcknowledgedWithAnEmptyJsonBody(): void
    {
        $ack = (new StripeTransferNotificationVerifier())->acknowledge();

        static::assertSame('', $ack->body);
        static::assertSame('application/json', $ack->contentType);
    }

    /**
     * @return array<string, string>
     */
    private function credentials(): array
    {
        return [StripeApi::WEBHOOK_SECRET => self::SECRET];
    }

    private function verifier(): StripeTransferNotificationVerifier
    {
        return new StripeTransferNotificationVerifier();
    }
}
