<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayQueryAction;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayQueryAction::class)]
class AlipayQueryActionTest extends TestCase
{
    public function testSupportsOnlyQueryRequestsWithAPaymentGatewayModel(): void
    {
        $action = new AlipayQueryAction();

        static::assertTrue($action->supports(new PaymentQuery(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new PaymentQuery(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayQueryAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayQueryAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(AlipayQueryAction::class));

        $action->execute(new PaymentQuery(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new AlipayQueryAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(AlipayQueryAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheQueryRequest(): void
    {
        $action = new AlipayQueryAction();
        // without the app id the sdk fails while it is built, before it would talk to the channel
        $action->setApi($this->api());

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::CURRENCY => 'CNY',
        ]);

        try {
            $action->execute(new PaymentQuery($model));
            static::fail('The query was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('P-1', $request['out_trade_no']);
        static::assertSame('CNY', $request['currency']);

        $response = $model->response();
        static::assertArrayHasKey('error', $response);
    }

    private function api(): AlipayApi
    {
        $channel = new PaymentChannel(AlipayApi::CHANNEL_H5, 'alipay', AlipayApi::CHANNEL_H5, PaymentFlow::REDIRECT);

        return new AlipayApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'alipay',
                    'alipay',
                    [],
                    [AlipayApi::CHANNEL_H5 => $channel],
                    new PaymentGatewayFactory('alipay', AlipayApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: [],
                params: [],
                runtimeParams: [],
                settings: [],
            ),
            'https://example.com/notify',
            '',
        );
    }
}
