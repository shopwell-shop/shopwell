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
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
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
     * Payum writes the api and the gateway onto the action at execution time. Sharing one action
     * between two gateways would therefore run the payment of one app with the credentials of the
     * other - invisible in a sequential request, fatal in a long running worker. Every gateway has to
     * be assembled with its own action.
     */
    public function testEveryGatewayIsAssembledWithItsOwnAction(): void
    {
        $prototype = new WechatCaptureAction();
        $factory = new PaymentGatewayFactory('wechat', WechatApi::class, [$prototype]);

        $first = $factory->createForPayment($this->resolved('wechat', 'wechat_h5'), '', '');
        $second = $factory->createForPayment($this->resolved('wechat', 'wechat_h5'), '', '');

        $firstAction = self::actionsOf($first)[0];
        $secondAction = self::actionsOf($second)[0];

        static::assertNotSame($prototype, $firstAction);
        static::assertNotSame($firstAction, $secondAction);

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'Order subject',
        ]);

        try {
            $first->execute(new Capture($model));
        } catch (\Throwable) {
            // the credentials are missing on purpose - only the injection of the api matters here
        }

        static::assertNotNull(self::apiOf($firstAction));
        static::assertNull(self::apiOf($secondAction), 'the second gateway must stay untouched');
        static::assertNull(self::apiOf($prototype), 'the prototype in the container must never be executed on');
    }

    /**
     * @return list<object>
     */
    private static function actionsOf(Gateway $gateway): array
    {
        $actions = new \ReflectionProperty(Gateway::class, 'actions');

        /** @var list<object> $value */
        $value = $actions->getValue($gateway);

        return array_values($value);
    }

    private static function apiOf(object $action): ?object
    {
        // declared on the abstract action, so it is not found by reflecting the concrete class
        $api = new \ReflectionProperty(AbstractPaymentAction::class, 'api');

        /** @var object|null $value */
        $value = $api->getValue($action);

        return $value;
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $params
     */
    private function resolved(string $provider, string $channel, array $credentials = [], array $params = []): ResolvedPaymentConfig
    {
        $channelOfPayment = new PaymentChannel($channel, $provider, $channel, PaymentFlow::REDIRECT);

        return new ResolvedPaymentConfig(
            provider: new PaymentProvider(
                $provider,
                $provider,
                [],
                [$channel => $channelOfPayment],
                static::createStub(PaymentGatewayFactory::class),
                static::createStub(PaymentNotificationExtractorInterface::class),
            ),
            channel: $channelOfPayment,
            credentials: $credentials,
            params: $params,
            runtimeParams: [],
            settings: [],
            usesPlatformAccount: true,
        );
    }
}
