<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayCaptureAction::class)]
class AlipayCaptureActionTest extends TestCase
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $credentials = null;

    public function testSupportsOnlyCaptureRequestsWithAPaymentGatewayModel(): void
    {
        $action = new AlipayCaptureAction();

        static::assertTrue($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Refund(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayCaptureAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Refund(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayCaptureAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(AlipayCaptureAction::class));

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new AlipayCaptureAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(AlipayCaptureAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteThrowsForAChannelTheAdapterCannotOpen(): void
    {
        $action = new AlipayCaptureAction();
        $action->setApi($this->api('alipay_unknown'));

        $this->expectExceptionObject(PaymentException::channelNotSupported('alipay_unknown', 'alipay'));

        $action->execute(new Capture($this->orderModel()));
    }

    public function testTheMobileWebChannelAnswersARedirectUrl(): void
    {
        $action = new AlipayCaptureAction();
        $action->setApi($this->api(AlipayApi::CHANNEL_H5, self::credentials()));

        $model = $this->orderModel();
        $action->execute(new Capture($model));

        $result = $model->result(PayResult::class, AlipayApi::CHANNEL_H5);

        static::assertSame(PaymentFlow::REDIRECT, $result->params->flow);
        static::assertSame('GET', $result->params->httpMethod);
        static::assertNull($result->params->htmlForm);
        static::assertSame(['method' => AlipayCaptureAction::METHOD_WAP_PAY], $result->params->extra);
        static::assertNotNull($result->params->payUrl);
        static::assertStringStartsWith('https://openapi.alipay.com/gateway.do?', $result->params->payUrl);

        $request = $model->request();
        static::assertSame(AlipayCaptureAction::METHOD_WAP_PAY, $request['method']);

        $content = $request['content'];
        static::assertIsArray($content);
        static::assertSame('P-1', $content['out_trade_no']);
        static::assertSame('12.34', $content['total_amount']);
        static::assertSame('Order subject', $content['subject']);
        static::assertSame(AlipayCaptureAction::PRODUCT_CODE_WAP, $content['product_code']);
        static::assertSame('the body', $content['body']);
        static::assertSame('attach-1', $content['passback_params']);
        static::assertSame('https://example.com/return', $content['quit_url']);

        $response = $model->response();
        static::assertSame('GET', $response['http_method']);
        static::assertSame($result->params->payUrl, $response['answer']);
    }

    public function testTheDesktopWebChannelAnswersASelfSubmittingFormOnPost(): void
    {
        $action = new AlipayCaptureAction();
        $action->setApi($this->api(
            AlipayApi::CHANNEL_WEB,
            self::credentials(),
            [AlipayCaptureAction::SETTING_HTTP_METHOD => 'post']
        ));

        $model = $this->orderModel();
        $action->execute(new Capture($model));

        $result = $model->result(PayResult::class, AlipayApi::CHANNEL_WEB);

        static::assertSame(PaymentFlow::REDIRECT, $result->params->flow);
        static::assertSame('POST', $result->params->httpMethod);
        static::assertNull($result->params->payUrl);
        static::assertNotNull($result->params->htmlForm);
        static::assertStringContainsString(AlipayCaptureAction::METHOD_PAGE_PAY, $result->params->htmlForm);
        static::assertSame(['method' => AlipayCaptureAction::METHOD_PAGE_PAY], $result->params->extra);

        $request = $model->request();
        $content = $request['content'];
        static::assertIsArray($content);
        static::assertSame(AlipayCaptureAction::PRODUCT_CODE_PAGE, $content['product_code']);

        $response = $model->response();
        static::assertSame('POST', $response['http_method']);
    }

    public function testTheAppChannelAnswersASignedOrderString(): void
    {
        $action = new AlipayCaptureAction();
        $action->setApi($this->api(AlipayApi::CHANNEL_APP, self::credentials()));

        $model = $this->orderModel();
        $action->execute(new Capture($model));

        $result = $model->result(PayResult::class, AlipayApi::CHANNEL_APP);

        static::assertSame(PaymentFlow::SDK, $result->params->flow);
        static::assertSame(AlipayCaptureAction::METHOD_APP_PAY, $result->params->extra['method']);

        $orderString = $result->params->extra['order_str'];
        static::assertIsString($orderString);
        static::assertStringContainsString('app_id=2021000000000000', $orderString);
        static::assertStringContainsString('method=' . AlipayCaptureAction::METHOD_APP_PAY, $orderString);
    }

    public function testTheExpiryIsSentAsMinutes(): void
    {
        $clock = new MockClock('2024-01-01T00:00:00+00:00');

        $action = new AlipayCaptureAction($clock);
        $action->setApi($this->api(AlipayApi::CHANNEL_H5, self::credentials()));

        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::EXPIRE_AT, $clock->now()->modify('+5 minutes'));

        $action->execute(new Capture($model));

        $content = $model->request()['content'];
        static::assertIsArray($content);
        static::assertSame('5m', $content['timeout_express']);
    }

    public function testAnExpiryInThePastIsSentAsTheMinimumOfOneMinute(): void
    {
        $clock = new MockClock('2024-01-01T00:00:00+00:00');

        $action = new AlipayCaptureAction($clock);
        $action->setApi($this->api(AlipayApi::CHANNEL_H5, self::credentials()));

        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::EXPIRE_AT, $clock->now()->modify('-5 minutes'));

        $action->execute(new Capture($model));

        $content = $model->request()['content'];
        static::assertIsArray($content);
        static::assertSame('1m', $content['timeout_express']);
    }

    public function testTheQrChannelWrapsAGatewayFailure(): void
    {
        $action = new AlipayCaptureAction();
        // without the app id the sdk fails while it is built, before it would talk to the channel
        $action->setApi($this->api(AlipayApi::CHANNEL_QR));

        $model = $this->orderModel();

        try {
            $action->execute(new Capture($model));
            static::fail('The qr capture was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('alipay.trade.precreate', $request['method']);

        $response = $model->response();
        static::assertArrayHasKey('error', $response);
    }

    public function testTheMiniProgramChannelWrapsAGatewayFailure(): void
    {
        $action = new AlipayCaptureAction();
        $action->setApi($this->api(AlipayApi::CHANNEL_MINI));

        $model = $this->orderModel();

        try {
            $action->execute(new Capture($model));
            static::fail('The mini program capture was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('alipay.trade.create', $request['method']);

        $response = $model->response();
        static::assertArrayHasKey('error', $response);
    }

    private function orderModel(): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 1234,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'Order subject',
            PaymentGatewayModel::BODY => 'the body',
            PaymentGatewayModel::ATTACH => 'attach-1',
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $settings
     */
    private function api(string $channel, array $credentials = [], array $settings = []): AlipayApi
    {
        $channelOfPayment = new PaymentChannel($channel, 'alipay', $channel, PaymentFlow::REDIRECT);

        return new AlipayApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'alipay',
                    'alipay',
                    [],
                    [$channel => $channelOfPayment],
                    new PaymentGatewayFactory('alipay', AlipayApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channelOfPayment,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: $settings,
            ),
            'https://example.com/notify',
            'https://example.com/return',
        );
    }

    /**
     * @return array<string, string>
     */
    private static function credentials(): array
    {
        if (self::$credentials !== null) {
            return self::$credentials;
        }

        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        static::assertIsArray($details);

        return self::$credentials = [
            AlipayApi::APP_ID => '2021000000000000',
            AlipayApi::PRIVATE_KEY => (string) $privateKey,
            AlipayApi::PUBLIC_KEY => (string) $details['key'],
        ];
    }
}
