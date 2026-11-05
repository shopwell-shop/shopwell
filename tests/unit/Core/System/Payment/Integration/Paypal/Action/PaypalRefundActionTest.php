<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Paypal\Action;

use PaypalServerSdkLib\Controllers\OrdersController;
use PaypalServerSdkLib\Controllers\PaymentsController;
use PaypalServerSdkLib\Http\ApiResponse;
use PaypalServerSdkLib\PaypalServerSdkClient;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentRefundRejectedException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalRefundAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Api\PaypalApi;
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
#[CoversClass(PaypalRefundAction::class)]
class PaypalRefundActionTest extends TestCase
{
    public function testQueriesAnAcceptedRefundWithoutSubmittingAgain(): void
    {
        $action = $this->queryAction(['status' => 'COMPLETED', 'id' => 'remote-refund']);
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

    public function testAConfirmedFailedQueryReleasesTheReservation(): void
    {
        $action = $this->queryAction(['status' => 'FAILED']);

        $this->expectException(PaymentRefundRejectedException::class);

        $action->execute(new RefundQuery(new PaymentGatewayModel(), 'remote-refund'));
    }

    public function testAnUnansweredSubmissionIsFoundOnTheOriginalPaypalOrder(): void
    {
        $capture = static::createStub(ApiResponse::class);
        $capture->method('getBody')->willReturn(json_encode([
            'supplementary_data' => ['related_ids' => ['order_id' => 'original-order']],
        ], \JSON_THROW_ON_ERROR));
        $order = static::createStub(ApiResponse::class);
        $order->method('getBody')->willReturn(json_encode([
            'purchase_units' => [['payments' => ['refunds' => [
                ['id' => 'unrelated-refund', 'custom_id' => 'OTHER', 'status' => 'COMPLETED'],
                ['id' => 'accepted-refund', 'custom_id' => 'REFUND-1', 'status' => 'COMPLETED'],
            ]]]],
        ], \JSON_THROW_ON_ERROR));
        $payments = static::createMock(PaymentsController::class);
        $payments->expects($this->never())->method('refundCapturedPayment');
        $payments->expects($this->once())->method('getCapturedPayment')->with(['captureId' => 'CAP-1'])->willReturn($capture);
        $orders = static::createMock(OrdersController::class);
        $orders->expects($this->once())->method('getOrder')->with(['id' => 'original-order'])->willReturn($order);
        $client = static::createStub(PaypalServerSdkClient::class);
        $client->method('getPaymentsController')->willReturn($payments);
        $client->method('getOrdersController')->willReturn($orders);
        $action = new PaypalRefundAction();
        $action->setApi($this->api($client));
        $model = $this->refundModel();

        $action->execute(new RefundQuery($model));

        static::assertSame('accepted-refund', $model->result(RefundResult::class, 'paypal')->outRefundId);
    }

    public function testSupportsOnlyRefundRequestsWithAPaymentGatewayModel(): void
    {
        $action = new PaypalRefundAction();

        static::assertTrue($action->supports(new Refund(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Refund(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalRefundAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(PaypalRefundAction::class));

        $action->execute(new Refund(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWhenThePaymentHasNoCaptureYet(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::AMOUNT => 25,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
        ]);

        $action = new PaypalRefundAction();
        $action->setApi($this->api());

        $this->expectExceptionObject(PaymentGatewayException::gatewayError(
            'paypal_checkout',
            'the payment has no capture at paypal yet and therefore cannot be refunded'
        ));

        $action->execute(new Refund($model));
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheRefundRequest(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::OUT_PAYMENT_NO => 'CAP-1',
            PaymentGatewayModel::AMOUNT => 25,
            PaymentGatewayModel::ORDER_AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
            PaymentGatewayModel::REASON => 'customer asked',
        ]);

        $action = new PaypalRefundAction();
        // without credentials the sdk client cannot be built, so the action fails before any http call
        $action->setApi($this->api());

        try {
            $action->execute(new Refund($model));
            static::fail('The refund was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        static::assertSame([
            'capture_id' => 'CAP-1',
            'custom_id' => 'REFUND-1',
            'amount' => '0.25',
            'currency' => 'CNY',
        ], $model->request());
    }

    public function testACompletedRefundFinishesTheRefund(): void
    {
        $action = $this->refundAction(['id' => 'REF-1', 'status' => 'COMPLETED']);

        $model = $this->refundModel();
        $action->execute(new Refund($model));

        $result = $model->result(RefundResult::class, 'paypal_checkout');

        static::assertSame(PaymentRefundStates::STATE_FINISHED, $result->status);
        static::assertSame('REF-1', $result->outRefundId);
    }

    public function testAPendingRefundStaysInProcessing(): void
    {
        $action = $this->refundAction(['id' => 'REF-1', 'status' => 'PENDING']);

        $model = $this->refundModel();
        $action->execute(new Refund($model));

        static::assertSame(
            PaymentRefundStates::STATE_PROCESSING,
            $model->result(RefundResult::class, 'paypal_checkout')->status
        );
    }

    public function testAFailedRefundIsAGatewayError(): void
    {
        $action = $this->refundAction(['id' => 'REF-1', 'status' => 'FAILED']);

        try {
            $action->execute(new Refund($this->refundModel()));
        } catch (PaymentException $exception) {
            static::assertStringContainsString('FAILED', $exception->getMessage());

            return;
        }

        static::fail('The refund was expected to fail for a refused refund.');
    }

    /**
     * @param array<string, mixed> $answer
     */
    private function refundAction(array $answer): PaypalRefundAction
    {
        $response = static::createStub(ApiResponse::class);
        $response->method('getBody')->willReturn(json_encode($answer, \JSON_THROW_ON_ERROR));

        $controller = static::createStub(PaymentsController::class);
        $controller->method('refundCapturedPayment')->willReturn($response);

        $client = static::createStub(PaypalServerSdkClient::class);
        $client->method('getPaymentsController')->willReturn($controller);

        $action = new PaypalRefundAction();
        $action->setApi($this->api($client));

        return $action;
    }

    private function refundModel(): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
            PaymentGatewayModel::OUT_PAYMENT_NO => 'CAP-1',
            PaymentGatewayModel::AMOUNT => 25,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
            PaymentGatewayModel::REASON => 'broken',
        ]);
    }

    /**
     * @param array<string, mixed> $answer
     */
    private function queryAction(array $answer): PaypalRefundAction
    {
        $response = static::createStub(ApiResponse::class);
        $response->method('getBody')->willReturn(json_encode($answer, \JSON_THROW_ON_ERROR));
        $controller = static::createMock(PaymentsController::class);
        $controller->expects($this->never())->method('refundCapturedPayment');
        $controller->expects($this->once())->method('getRefund')->with(['refundId' => 'remote-refund'])->willReturn($response);
        $client = static::createStub(PaypalServerSdkClient::class);
        $client->method('getPaymentsController')->willReturn($controller);
        $action = new PaypalRefundAction();
        $action->setApi($this->api($client));

        return $action;
    }

    private function api(?PaypalServerSdkClient $client = null): PaypalApi
    {
        $channel = new PaymentProduct('paypal_checkout', 'paypal', 'paypal_checkout', [PaymentInteraction::REDIRECT], 'paypal');

        return new PaypalApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'paypal',
                    'paypal',
                    [],
                    ['paypal_checkout' => $channel],
                    new PaymentGatewayFactory('paypal', PaypalApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                product: $channel,
                credentials: [],
                params: [],
                runtimeParams: [],
                settings: [],
                accountId: 'test-account',
            ),
            'https://example.com/notify',
            'https://example.com/return',
            null,
            $client,
        );
    }
}
