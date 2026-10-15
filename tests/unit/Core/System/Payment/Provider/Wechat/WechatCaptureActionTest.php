<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCaptureAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatCaptureAction::class)]
class WechatCaptureActionTest extends TestCase
{
    public function testSupportsOnlyCaptureRequestsWithAPaymentGatewayModel(): void
    {
        $action = new WechatCaptureAction();

        static::assertTrue($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Refund(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatCaptureAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(WechatCaptureAction::class));

        $action->execute(new Capture(new PaymentGatewayModel()));
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
        $action->setApi($this->api(WechatApi::CHANNEL_H5, params: [WechatApi::PARAM_APP_ID => '']));

        $this->expectExceptionObject(PaymentException::channelNotConfigured('wechat_h5', WechatApi::PARAM_APP_ID));

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

        $request = $model->request();
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
        yield 'h5' => [WechatApi::CHANNEL_H5];
        yield 'native' => [WechatApi::CHANNEL_NATIVE];
        yield 'jsapi' => [WechatApi::CHANNEL_JSAPI];
        yield 'mini' => [WechatApi::CHANNEL_MINI];
        yield 'app' => [WechatApi::CHANNEL_APP];
    }

    public function testH5PayloadCarriesTheSceneInfoAttachAndExpiry(): void
    {
        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::ATTACH, 'attach-value');
        $model->offsetSet(PaymentGatewayModel::CLIENT_IP, '203.0.113.7');
        $model->offsetSet(PaymentGatewayModel::EXPIRE_AT, new \DateTimeImmutable('2026-01-01T00:00:00+08:00'));

        $action = new WechatCaptureAction();
        $action->setApi($this->api(WechatApi::CHANNEL_H5, settings: [WechatCaptureAction::SETTING_H5_TYPE => 'MWEB']));

        $this->failExecution($action, $model);

        $request = $model->request();
        static::assertSame('attach-value', $request['attach']);
        static::assertSame('2026-01-01T00:00:00+08:00', $request['time_expire']);
        static::assertSame([
            'payer_client_ip' => '203.0.113.7',
            'h5_info' => ['type' => 'MWEB'],
        ], $request['scene_info']);

        $response = $model->response();
        static::assertArrayHasKey('error', $response);
    }

    public function testDescriptionIsCutAtTheChannelLimit(): void
    {
        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::SUBJECT, str_repeat('a', 200));

        $action = new WechatCaptureAction();
        $action->setApi($this->api(WechatApi::CHANNEL_H5));

        $this->failExecution($action, $model);

        $request = $model->request();
        static::assertSame(127, \strlen((string) $request['description']));
    }

    private function failExecution(WechatCaptureAction $action, PaymentGatewayModel $model): void
    {
        try {
            $action->execute(new Capture($model));
            static::fail('The capture was expected to fail without a complete merchant certificate.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }
    }

    private function orderModel(): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
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
        $channelOfPayment = new PaymentChannel($channel, 'wechat', $channel, PaymentFlow::REDIRECT);

        return new WechatApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'wechat',
                    'wechat',
                    [],
                    [$channel => $channelOfPayment],
                    new PaymentGatewayFactory('wechat', WechatApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channelOfPayment,
                credentials: [WechatApi::MCH_ID => '1900000001'],
                params: array_replace([WechatApi::PARAM_APP_ID => 'wx-app-id'], $params),
                runtimeParams: [],
                settings: $settings,
                mode: 'live',
            ),
            'https://example.com/notify',
            '',
        );
    }
}
