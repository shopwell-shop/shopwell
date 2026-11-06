<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Alipay\Action;

use Alipay\OpenAPISDK\Api\AlipayTradeFastpayRefundApi;
use Alipay\OpenAPISDK\Model\AlipayTradeFastpayRefundQueryResponseModel;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayRefundAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\Integration\Alipay\Product\AlipayProducts;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayRefundAction::class)]
class AlipayRefundActionTest extends TestCase
{
    public function testQueriesAnAcceptedRefundWithoutSubmittingAgain(): void
    {
        $action = $this->queryAction(['refund_status' => 'REFUND_SUCCESS', 'trade_no' => 'remote-refund']);
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::OUT_PAYMENT_NO => 'remote-payment',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
        ]);

        $action->execute(new RefundQuery($model, 'remote-refund'));

        $result = $model->result(RefundResult::class, 'channel');
        static::assertSame(PaymentRefundStates::STATE_FINISHED, $result->status);
        static::assertSame('remote-refund', $result->outRefundId);
    }

    public function testAnUnrecognizedQueryAnswerKeepsTheRefundReserved(): void
    {
        $action = $this->queryAction([]);
        $model = new PaymentGatewayModel();

        $action->execute(new RefundQuery($model, 'remote-refund'));

        $result = $model->result(RefundResult::class, 'channel');
        static::assertSame(PaymentRefundStates::STATE_PROCESSING, $result->status);
        static::assertSame('remote-refund', $result->outRefundId);
    }

    public function testSupportsOnlyRefundRequestsWithAPaymentGatewayModel(): void
    {
        $action = new AlipayRefundAction();

        static::assertTrue($action->supports(new Refund(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Refund(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayRefundAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayRefundAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(AlipayRefundAction::class));

        $action->execute(new Refund(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new AlipayRefundAction();

        $this->expectExceptionObject(PaymentGatewayException::unsupportedApi(AlipayRefundAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheRefundRequest(): void
    {
        $action = new AlipayRefundAction();
        // without the app id the sdk fails while it is built, before it would talk to the channel
        $action->setApi($this->api());

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 1234,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'R-1',
            PaymentGatewayModel::REASON => 'broken',
        ]);

        try {
            $action->execute(new Refund($model));
            static::fail('The refund was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('P-1', $request['out_trade_no']);
        static::assertSame('R-1', $request['out_request_no']);
        static::assertSame('12.34', $request['refund_amount']);

        $response = $model->response();
        static::assertArrayHasKey('error', $response);
    }

    /**
     * @param array<string, mixed> $answer
     */
    private function queryAction(array $answer): AlipayRefundAction
    {
        $response = new AlipayTradeFastpayRefundQueryResponseModel();
        $response->setTradeNo($answer['trade_no'] ?? null);
        $response->setRefundStatus($answer['refund_status'] ?? null);
        $sdk = static::createMock(AlipayTradeFastpayRefundApi::class);
        $sdk->expects($this->once())->method('query')->willReturn($response);
        $action = new AlipayRefundAction();
        $action->setApi($this->api(refundQueryApi: $sdk));

        return $action;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = [], ?AlipayTradeFastpayRefundApi $refundQueryApi = null): AlipayApi
    {
        $channel = new PaymentProduct(AlipayProducts::H5, 'alipay', AlipayProducts::H5, [PaymentInteraction::REDIRECT], 'alipay');

        return new AlipayApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'alipay',
                    'alipay',
                    [],
                    [AlipayProducts::H5 => $channel],
                    new PaymentGatewayFactory('alipay', AlipayApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                product: $channel,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: [],
                accountId: 'test-account',
            ),
            'https://example.com/notify',
            '',
            refundQueryApi: $refundQueryApi,
        );
    }
}
