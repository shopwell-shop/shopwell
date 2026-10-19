<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
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
        $api = $this->api(credentials: [StripeApi::SECRET_KEY => 'sk_test_123']);

        static::assertSame('stripe', $api->provider());
        static::assertSame('stripe_checkout', $api->channel());
        static::assertSame('own', $api->mode());
        static::assertSame([StripeApi::SECRET_KEY => 'sk_test_123'], $api->credentials());
        static::assertSame('https://example.com/notify', $api->notifyUrl());
        static::assertSame('https://example.com/return', $api->returnUrl());
    }

    public function testWebhookSecretReturnsTheCredential(): void
    {
        $api = $this->api(credentials: [StripeApi::WEBHOOK_SECRET => 'whsec_123']);

        static::assertSame('whsec_123', $api->webhookSecret());
    }

    public function testWebhookSecretThrowsWhenTheCredentialIsMissing(): void
    {
        $api = $this->api();

        $this->expectExceptionObject(PaymentException::missingCredentials('stripe', StripeApi::WEBHOOK_SECRET));

        $api->webhookSecret();
    }

    public function testPublishableKeyReturnsTheCredential(): void
    {
        $api = $this->api(credentials: [StripeApi::PUBLISHABLE_KEY => 'pk_test_123']);

        static::assertSame('pk_test_123', $api->publishableKey());
    }

    public function testPublishableKeyDefaultsToAnEmptyString(): void
    {
        $api = $this->api();

        static::assertSame('', $api->publishableKey());
    }

    public function testCurrencyIsLowercased(): void
    {
        $api = $this->api();

        static::assertSame('cny', $api->currency('CNY'));
        static::assertSame('usd', $api->currency('usd'));
        static::assertSame('', $api->currency(''));
    }

    public function testClientIsBuiltOnceAndCached(): void
    {
        $api = $this->api(credentials: [StripeApi::SECRET_KEY => 'sk_test_123']);

        $client = $api->client();

        static::assertSame($client, $api->client());
    }

    public function testClientIsBuiltWithAnApiVersion(): void
    {
        $api = $this->api(credentials: [
            StripeApi::SECRET_KEY => 'sk_test_123',
            StripeApi::API_VERSION => '2024-06-20',
        ]);

        static::assertSame('2024-06-20', $api->client()->getStripeVersion());
    }

    public function testClientThrowsWithoutTheSecretKey(): void
    {
        $api = $this->api();

        $this->expectExceptionObject(PaymentException::missingCredentials('stripe', StripeApi::SECRET_KEY));

        $api->client();
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): StripeApi
    {
        $channel = new PaymentChannel('stripe_checkout', 'stripe', 'stripe_checkout', PaymentFlow::REDIRECT);

        return new StripeApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'stripe',
                    'stripe',
                    [],
                    ['stripe_checkout' => $channel],
                    new PaymentGatewayFactory('stripe', StripeApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: [],
                mode: 'own',
            ),
            'https://example.com/notify',
            'https://example.com/return',
        );
    }
}
