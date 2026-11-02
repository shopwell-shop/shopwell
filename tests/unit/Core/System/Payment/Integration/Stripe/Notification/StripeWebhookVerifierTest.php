<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Stripe\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi;
use Shopwell\Core\System\Payment\Integration\Stripe\Notification\StripeWebhookVerifier;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeWebhookVerifier::class)]
class StripeWebhookVerifierTest extends TestCase
{
    private const SECRET = 'whsec_unit_test_secret';

    public function testAnEmptySignatureConfirmsNothing(): void
    {
        static::assertFalse(StripeWebhookVerifier::verify($this->credentials(), '{}', ''));
    }

    public function testAMissingSecretIsAConfigurationError(): void
    {
        $this->expectExceptionObject(PaymentException::missingCredentials(StripeApi::PROVIDER, StripeApi::WEBHOOK_SECRET));

        StripeWebhookVerifier::verify([], '{}', $this->signature('{}'));
    }

    public function testABodySignedWithTheSecretOfTheEndpointIsVerified(): void
    {
        $body = '{"id":"evt-1"}';

        static::assertTrue(StripeWebhookVerifier::verify($this->credentials(), $body, $this->signature($body)));
    }

    public function testABodyThatWasChangedAfterItWasSignedIsNotVerified(): void
    {
        static::assertFalse(StripeWebhookVerifier::verify($this->credentials(), '{"id":"evt-2"}', $this->signature('{"id":"evt-1"}')));
    }

    public function testASignatureOfAnotherSecretIsNotVerified(): void
    {
        $body = '{"id":"evt-1"}';

        static::assertFalse(StripeWebhookVerifier::verify(
            [StripeApi::WEBHOOK_SECRET => 'whsec_another_secret'],
            $body,
            $this->signature($body)
        ));
    }

    /**
     * @return array<string, string>
     */
    private function credentials(): array
    {
        return [StripeApi::WEBHOOK_SECRET => self::SECRET];
    }

    /**
     * The header of a stripe webhook, `t=<timestamp>,v1=<hmac of timestamp.body>`.
     */
    private function signature(string $rawBody): string
    {
        $timestamp = '1700000000';

        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, self::SECRET);
    }
}
