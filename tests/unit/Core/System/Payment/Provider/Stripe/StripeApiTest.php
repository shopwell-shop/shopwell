<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeApi::class)]
class StripeApiTest extends TestCase
{
    public function testExposesTheConfigurationOfThePayment(): void
    {
        $api = new StripeApi($this->config(credentials: [StripeApi::SECRET_KEY => 'sk_test_123']));

        static::assertSame('stripe', $api->provider());
        static::assertSame('stripe_checkout', $api->channel());
        static::assertSame('own', $api->mode());
        static::assertSame([StripeApi::SECRET_KEY => 'sk_test_123'], $api->credentials());
        static::assertSame('https://example.com/notify', $api->notifyUrl());
        static::assertSame('https://example.com/return', $api->returnUrl());
    }

    public function testWebhookSecretReturnsTheCredential(): void
    {
        $api = new StripeApi($this->config(credentials: [StripeApi::WEBHOOK_SECRET => 'whsec_123']));

        static::assertSame('whsec_123', $api->webhookSecret());
    }

    public function testWebhookSecretThrowsWhenTheCredentialIsMissing(): void
    {
        $api = new StripeApi($this->config());

        $this->expectExceptionObject(PaymentException::missingCredentials('stripe', StripeApi::WEBHOOK_SECRET));

        $api->webhookSecret();
    }

    public function testPublishableKeyReturnsTheCredential(): void
    {
        $api = new StripeApi($this->config(credentials: [StripeApi::PUBLISHABLE_KEY => 'pk_test_123']));

        static::assertSame('pk_test_123', $api->publishableKey());
    }

    public function testPublishableKeyDefaultsToAnEmptyString(): void
    {
        $api = new StripeApi($this->config());

        static::assertSame('', $api->publishableKey());
    }

    public function testCurrencyIsLowercased(): void
    {
        $api = new StripeApi($this->config());

        static::assertSame('cny', $api->currency('CNY'));
        static::assertSame('usd', $api->currency('usd'));
        static::assertSame('', $api->currency(''));
    }

    public function testClientIsBuiltOnceAndCached(): void
    {
        $api = new StripeApi($this->config(credentials: [StripeApi::SECRET_KEY => 'sk_test_123']));

        $client = $api->client();

        static::assertSame($client, $api->client());
    }

    public function testClientIsBuiltWithAnApiVersion(): void
    {
        $api = new StripeApi($this->config(credentials: [
            StripeApi::SECRET_KEY => 'sk_test_123',
            StripeApi::API_VERSION => '2024-06-20',
        ]));

        static::assertSame('2024-06-20', $api->client()->getStripeVersion());
    }

    public function testClientThrowsWithoutTheSecretKey(): void
    {
        $api = new StripeApi($this->config());

        $this->expectExceptionObject(PaymentException::missingCredentials('stripe', StripeApi::SECRET_KEY));

        $api->client();
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @return array<string, mixed>
     */
    private function config(array $credentials = []): array
    {
        return [
            AbstractPaymentApi::CONFIG_PROVIDER => 'stripe',
            AbstractPaymentApi::CONFIG_CHANNEL => 'stripe_checkout',
            AbstractPaymentApi::CONFIG_MODE => 'own',
            AbstractPaymentApi::CONFIG_CREDENTIALS => $credentials,
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => 'https://example.com/return',
        ];
    }
}
