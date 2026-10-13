<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Service;

use Payum\Core\Gateway;
use Payum\Core\GatewayFactoryInterface;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeGatewayFactory;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;
use Shopwell\Core\System\Payment\Service\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Struct\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentGatewayFactory::class)]
class PaymentGatewayFactoryTest extends TestCase
{
    public function testCreateTranslatesTheResolvedConfigIntoThePayumConfig(): void
    {
        $channel = new PaymentChannel('stripe_checkout', 'stripe', 'Stripe Checkout', PaymentFlow::REDIRECT);

        $gateway = new Gateway();
        $gatewayFactory = $this->createMock(GatewayFactoryInterface::class);
        $gatewayFactory->expects($this->once())
            ->method('create')
            ->with([
                'payum.factory_name' => 'stripe',
                'payum.factory_title' => 'Stripe',
                AbstractPaymentApi::CONFIG_PROVIDER => 'stripe',
                AbstractPaymentApi::CONFIG_CHANNEL => 'stripe_checkout',
                AbstractPaymentApi::CONFIG_MODE => 'platform',
                AbstractPaymentApi::CONFIG_CREDENTIALS => ['secret_key' => 'sk_test_123'],
                AbstractPaymentApi::CONFIG_PARAMS => ['appid' => 'wx-platform'],
                AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => ['openid' => 'o-1'],
                AbstractPaymentApi::CONFIG_SETTINGS => ['flow' => 'sdk'],
                AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://shop.example.com/notify',
                AbstractPaymentApi::CONFIG_RETURN_URL => 'https://shop.example.com/return',
            ])
            ->willReturn($gateway);

        $provider = new PaymentProvider(
            'stripe',
            'Stripe',
            [],
            ['stripe_checkout' => $channel],
            $gatewayFactory,
            static::createStub(PaymentNotificationExtractorInterface::class)
        );

        $resolved = new ResolvedPaymentConfig(
            provider: $provider,
            channel: $channel,
            credentials: ['secret_key' => 'sk_test_123'],
            params: ['appid' => 'wx-platform'],
            runtimeParams: ['openid' => 'o-1'],
            settings: ['flow' => 'sdk'],
            mode: 'platform',
        );

        static::assertSame(
            $gateway,
            (new PaymentGatewayFactory())->create($resolved, 'https://shop.example.com/notify', 'https://shop.example.com/return')
        );
    }

    public function testCreateBuildsAGatewayThatDispatchesToTheStripeActions(): void
    {
        $channel = new PaymentChannel('stripe_checkout', 'stripe', 'Stripe Checkout', PaymentFlow::REDIRECT);

        $provider = new PaymentProvider(
            'stripe',
            'Stripe',
            [],
            ['stripe_checkout' => $channel],
            new StripeGatewayFactory(),
            new StripeNotificationExtractor()
        );

        $resolved = new ResolvedPaymentConfig($provider, $channel, [], [], [], [], 'platform');

        $gateway = (new PaymentGatewayFactory())->create($resolved, '', '');

        static::assertInstanceOf(Gateway::class, $gateway);

        $model = new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'Order subject',
        ]);

        try {
            $gateway->execute(new Capture($model));
            static::fail('The capture was expected to fail without stripe credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }
    }
}
