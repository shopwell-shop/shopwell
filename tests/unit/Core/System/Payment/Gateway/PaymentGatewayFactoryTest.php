<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use Payum\Core\Gateway;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Integration\Stripe\Action\StripeCaptureAction;
use Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatCaptureAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentGatewayFactory::class)]
class PaymentGatewayFactoryTest extends TestCase
{
    public function testTheFactoryNamesItselfAfterItsInterface(): void
    {
        $factory = new PaymentGatewayFactory('stripe', StripeApi::class, []);

        static::assertSame('stripe', $factory->provider());
        static::assertSame([
            'payum.factory_name' => 'stripe',
            'payum.factory_title' => 'stripe',
            PaymentGatewayFactory::CONFIG_RESOLVED => null,
            PaymentGatewayFactory::CONFIG_NOTIFY_URL => '',
            PaymentGatewayFactory::CONFIG_RETURN_URL => '',
        ], $factory->createConfig());
    }

    public function testTheGivenConfigWinsOverTheDefaults(): void
    {
        $factory = new PaymentGatewayFactory('stripe', StripeApi::class, []);

        $config = $factory->createConfig([
            'payum.factory_title' => 'Stripe',
            PaymentGatewayFactory::CONFIG_NOTIFY_URL => 'https://shop.example.com/notify',
        ]);

        static::assertSame('stripe', $config['payum.factory_name']);
        static::assertSame('Stripe', $config['payum.factory_title']);
        static::assertSame('https://shop.example.com/notify', $config[PaymentGatewayFactory::CONFIG_NOTIFY_URL]);
        static::assertSame('', $config[PaymentGatewayFactory::CONFIG_RETURN_URL]);
    }

    public function testCreateForPaymentAssemblesTheGatewayThatDispatchesToTheActions(): void
    {
        $factory = new PaymentGatewayFactory('stripe', StripeApi::class, [new StripeCaptureAction()]);

        $gateway = $factory->createForPayment($this->resolved('stripe', 'stripe_checkout'), '', '');

        static::assertInstanceOf(Gateway::class, $gateway);

        $model = new PaymentGatewayModel([
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

        // the action ran behind the api of the payment, so the request it built was recorded on the model
        static::assertSame('redirect', $model->request()['flow']);
    }

    public function testCreateForPaymentHandsTheResolvedParametersToTheApi(): void
    {
        $factory = new PaymentGatewayFactory('wechat', WechatApi::class, [new WechatCaptureAction()]);

        $resolved = $this->resolved('wechat', 'wechat_h5', [], ['appid' => 'wx-app-id']);
        $gateway = $factory->createForPayment($resolved, 'https://shop.example.com/notify', 'https://shop.example.com/return');

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'Order subject',
        ]);

        try {
            $gateway->execute(new Capture($model));
            static::fail('The capture was expected to fail without merchant credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::MISSING_CREDENTIALS, $exception->getErrorCode());
            // the action got past the appid of the channel, so the resolved parameters reached the api
            // and it failed on the missing merchant credentials instead
            static::assertSame(WechatApi::MCH_ID, $exception->getParameters()['field'] ?? null);
        }
    }

    /**
     * Payum writes the api onto an action at execution time. This test observes the real executions:
     * every gateway must use a different clone and read its own merchant marker, without ever running
     * the container prototype.
     */
    public function testEveryGatewayIsAssembledWithItsOwnAction(): void
    {
        $calls = new \ArrayObject();
        $prototype = new class($calls) extends AbstractPaymentAction {
            protected string $apiClass = WechatApi::class;

            /**
             * @param \ArrayObject<int, array{action: int, marker: mixed}> $calls
             */
            public function __construct(private readonly \ArrayObject $calls)
            {
            }

            public function capability(): PaymentCapability
            {
                return PaymentCapability::CAPTURE;
            }

            public function supports($request): bool
            {
                return $this->isSupported($request, Capture::class);
            }

            public function execute($request): void
            {
                $this->modelOf($request);
                $this->calls->append([
                    'action' => spl_object_id($this),
                    'marker' => $this->api()->credentials()['marker'] ?? null,
                ]);
            }
        };
        $factory = new PaymentGatewayFactory('wechat', WechatApi::class, [$prototype]);

        $first = $factory->createForPayment($this->resolved('wechat', 'wechat_h5', ['marker' => 'first']), '', '');
        $second = $factory->createForPayment($this->resolved('wechat', 'wechat_h5', ['marker' => 'second']), '', '');

        $first->execute(new Capture(new PaymentGatewayModel()));
        $second->execute(new Capture(new PaymentGatewayModel()));

        $executions = array_values($calls->getArrayCopy());
        static::assertSame(['first', 'second'], array_column($executions, 'marker'));
        static::assertNotSame($executions[0]['action'], $executions[1]['action']);
        static::assertNotContains(spl_object_id($prototype), array_column($executions, 'action'));
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $params
     */
    private function resolved(string $provider, string $channel, array $credentials = [], array $params = []): ResolvedPaymentConfig
    {
        $channelOfPayment = new PaymentProduct($channel, $provider, $channel, [PaymentInteraction::REDIRECT], $provider);

        return new ResolvedPaymentConfig(
            provider: new PaymentProvider(
                $provider,
                $provider,
                [],
                [$channel => $channelOfPayment],
                static::createStub(PaymentGatewayFactory::class),
                static::createStub(PaymentNotificationExtractorInterface::class),
            ),
            product: $channelOfPayment,
            credentials: $credentials,
            params: $params,
            runtimeParams: [],
            settings: [],
            accountId: 'test-account',
        );
    }
}
