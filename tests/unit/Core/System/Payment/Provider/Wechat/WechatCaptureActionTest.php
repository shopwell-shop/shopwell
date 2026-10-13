<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCaptureAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatGatewayFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatCaptureAction::class)]
class WechatCaptureActionTest extends TestCase
{
    public function testSupportsOnlyCaptureRequestsWithAnArrayObjectModel(): void
    {
        $action = new WechatCaptureAction();

        static::assertTrue($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Refund(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatCaptureAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(WechatCaptureAction::class));

        $action->execute(new Capture(new \ArrayObject()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new WechatCaptureAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(WechatCaptureAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteThrowsWhenTheAppIdOfTheChannelIsMissing(): void
    {
        $action = new WechatCaptureAction();
        $action->setApi($this->api(WechatGatewayFactory::CHANNEL_H5, params: [WechatGatewayFactory::PARAM_APP_ID => '']));

        $this->expectExceptionObject(PaymentException::channelNotConfigured('wechat_h5', WechatGatewayFactory::PARAM_APP_ID));

        $action->execute(new Capture($this->orderModel()));
    }

    public function testExecuteThrowsForAChannelTheAdapterCannotOpen(): void
    {
        $action = new WechatCaptureAction();
        $action->setApi($this->api('wechat_unknown'));

        $this->expectExceptionObject(PaymentException::channelNotSupported('wechat_unknown', 'wechat'));

        $action->execute(new Capture($this->orderModel()));
    }

    #[DataProvider('channelProvider')]
    public function testExecuteWrapsAGatewayFailureForEverySupportedChannel(string $channel): void
    {
        $model = $this->orderModel();

        $action = new WechatCaptureAction();
        // the merchant certificate is incomplete, so the sdk fails before it would talk to the channel
        $action->setApi($this->api($channel));

        try {
            $action->execute(new Capture($model));
            static::fail('The capture was expected to fail without a complete merchant certificate.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = PaymentGatewayModel::requestOf($model);
        static::assertSame('ORDER-1', $request['out_trade_no']);
        static::assertSame('wx-app-id', $request['appid']);
        static::assertSame('1900000001', $request['mchid']);
        static::assertSame(['total' => 100, 'currency' => 'CNY'], $request['amount']);
        static::assertSame('https://example.com/notify', $request['notify_url']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function channelProvider(): iterable
    {
        yield 'h5' => [WechatGatewayFactory::CHANNEL_H5];
        yield 'native' => [WechatGatewayFactory::CHANNEL_NATIVE];
        yield 'jsapi' => [WechatGatewayFactory::CHANNEL_JSAPI];
        yield 'mini' => [WechatGatewayFactory::CHANNEL_MINI];
        yield 'app' => [WechatGatewayFactory::CHANNEL_APP];
    }

    public function testH5PayloadCarriesTheSceneInfoAttachAndExpiry(): void
    {
        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::ATTACH, 'attach-value');
        $model->offsetSet(PaymentGatewayModel::CLIENT_IP, '203.0.113.7');
        $model->offsetSet(PaymentGatewayModel::EXPIRE_AT, new \DateTimeImmutable('2026-01-01T00:00:00+08:00'));

        $action = new WechatCaptureAction();
        $action->setApi($this->api(WechatGatewayFactory::CHANNEL_H5, settings: [WechatCaptureAction::SETTING_H5_TYPE => 'MWEB']));

        $this->failExecution($action, $model);

        $request = PaymentGatewayModel::requestOf($model);
        static::assertSame('attach-value', $request['attach']);
        static::assertSame('2026-01-01T00:00:00+08:00', $request['time_expire']);
        static::assertSame([
            'payer_client_ip' => '203.0.113.7',
            'h5_info' => ['type' => 'MWEB'],
        ], $request['scene_info']);

        $response = PaymentGatewayModel::responseOf($model);
        static::assertArrayHasKey('error', $response);
    }

    public function testDescriptionIsCutAtTheChannelLimit(): void
    {
        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::SUBJECT, str_repeat('a', 200));

        $action = new WechatCaptureAction();
        $action->setApi($this->api(WechatGatewayFactory::CHANNEL_H5));

        $this->failExecution($action, $model);

        $request = PaymentGatewayModel::requestOf($model);
        static::assertSame(127, \strlen((string) $request['description']));
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    private function failExecution(WechatCaptureAction $action, \ArrayObject $model): void
    {
        try {
            $action->execute(new Capture($model));
            static::fail('The capture was expected to fail without a complete merchant certificate.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    private function orderModel(): \ArrayObject
    {
        return new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'A subject',
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $settings
     */
    private function api(string $channel, array $params = [], array $settings = []): WechatApi
    {
        return new WechatApi([
            AbstractPaymentApi::CONFIG_PROVIDER => 'wechat',
            AbstractPaymentApi::CONFIG_CHANNEL => $channel,
            AbstractPaymentApi::CONFIG_MODE => 'live',
            AbstractPaymentApi::CONFIG_CREDENTIALS => [WechatApi::MCH_ID => '1900000001'],
            AbstractPaymentApi::CONFIG_PARAMS => array_replace([WechatGatewayFactory::PARAM_APP_ID => 'wx-app-id'], $params),
            AbstractPaymentApi::CONFIG_SETTINGS => $settings,
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => '',
        ]);
    }
}
