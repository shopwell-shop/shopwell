<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Paypal\Action;

use PaypalServerSdkLib\Controllers\OrdersController;
use PaypalServerSdkLib\Http\ApiResponse;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\PaypalServerSdkClient;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PayResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalCaptureAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Api\PaypalApi;
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
#[CoversClass(PaypalCaptureAction::class)]
class PaypalCaptureActionTest extends TestCase
{
    public function testSupportsOnlyCaptureRequestsWithAPaymentGatewayModel(): void
    {
        $action = new PaypalCaptureAction();

        static::assertTrue($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Refund(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalCaptureAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(PaypalCaptureAction::class));

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheOrderRequest(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::OUT_TRADE_NO => 'TRADE-1',
            PaymentGatewayModel::AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'A subject',
        ]);

        $action = new PaypalCaptureAction();
        // without credentials the sdk client cannot be built, so the action fails before any http call
        $action->setApi($this->api());

        try {
            $action->execute(new Capture($model));
            static::fail('The capture was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        static::assertSame([
            'intent' => CheckoutPaymentIntent::CAPTURE,
            'currency' => 'CNY',
            'amount' => '1.00',
            'payment_no' => 'ORDER-1',
        ], $model->request());
    }

    public function testExecuteUppercasesTheCurrencyOfTheOrder(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::AMOUNT => 5,
            PaymentGatewayModel::CURRENCY => 'usd',
        ]);

        $action = new PaypalCaptureAction();
        $action->setApi($this->api());

        try {
            $action->execute(new Capture($model));
            static::fail('The capture was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('USD', $request['currency']);
        static::assertSame('0.05', $request['amount']);
    }

    public function testTheHostedCheckoutAnswersTheApproveLink(): void
    {
        $action = new PaypalCaptureAction();
        $action->setApi($this->api($this->clientAnswering([
            'id' => 'ORDER-1',
            'status' => 'CREATED',
            'links' => [
                ['rel' => 'self', 'href' => 'https://api.paypal.test/v2/checkout/orders/ORDER-1'],
                ['rel' => 'approve', 'href' => 'https://paypal.test/approve?token=ORDER-1'],
            ],
        ])));

        $model = $this->orderModel();
        $action->execute(new Capture($model));

        $params = $model->result(PayResult::class, 'paypal_checkout')->params;

        static::assertSame(PaymentFlow::REDIRECT, $params->flow);
        static::assertSame('https://paypal.test/approve?token=ORDER-1', $params->payUrl);
        static::assertSame(['order_id' => 'ORDER-1'], $params->extra);

        // the answer of the interface is the evidence a support case reads
        static::assertSame('ORDER-1', $model->response()['id']);
        static::assertSame('CREATED', $model->response()['status']);
    }

    public function testTheSdkFlowAnswersTheOrderId(): void
    {
        $action = new PaypalCaptureAction();
        $action->setApi($this->api($this->clientAnswering([
            'id' => 'ORDER-1',
            'status' => 'CREATED',
            'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve?token=ORDER-1']],
        ]), [PaypalCaptureAction::SETTING_FLOW => PaypalCaptureAction::FLOW_SDK]));

        $model = $this->orderModel();
        $action->execute(new Capture($model));

        $params = $model->result(PayResult::class, 'paypal_checkout')->params;

        // the app approves the very same order with the javascript sdk, so it needs the id only
        static::assertSame(PaymentFlow::SDK, $params->flow);
        static::assertNull($params->payUrl);
        static::assertSame(['order_id' => 'ORDER-1'], $params->extra);
    }

    public function testAnOrderWithoutAnApproveLinkIsAGatewayError(): void
    {
        $action = new PaypalCaptureAction();
        $action->setApi($this->api($this->clientAnswering(['id' => 'ORDER-1', 'status' => 'CREATED'])));

        try {
            $action->execute(new Capture($this->orderModel()));
        } catch (PaymentException $exception) {
            static::assertStringContainsString('approval url', $exception->getMessage());

            return;
        }

        static::fail('The capture was expected to fail for an order without an approval link.');
    }

    public function testAnAnswerWithoutAnOrderIdIsAGatewayError(): void
    {
        $action = new PaypalCaptureAction();
        $action->setApi($this->api($this->clientAnswering(['status' => 'CREATED'])));

        try {
            $action->execute(new Capture($this->orderModel()));
        } catch (PaymentException $exception) {
            static::assertStringContainsString('did not answer an order', $exception->getMessage());

            return;
        }

        static::fail('The capture was expected to fail for an answer without an order id.');
    }

    private function orderModel(): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::OUT_TRADE_NO => 'TRADE-1',
            PaymentGatewayModel::AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'A subject',
        ]);
    }

    /**
     * A client whose orders controller answers the given order body.
     *
     * @param array<string, mixed> $answer
     */
    private function clientAnswering(array $answer): PaypalServerSdkClient
    {
        $response = static::createStub(ApiResponse::class);
        $response->method('getBody')->willReturn(json_encode($answer, \JSON_THROW_ON_ERROR));

        $controller = static::createStub(OrdersController::class);
        $controller->method('createOrder')->willReturn($response);

        $client = static::createStub(PaypalServerSdkClient::class);
        $client->method('getOrdersController')->willReturn($controller);

        return $client;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function api(?PaypalServerSdkClient $client = null, array $settings = [PaypalCaptureAction::SETTING_FLOW => PaypalCaptureAction::FLOW_REDIRECT]): PaypalApi
    {
        $channel = new PaymentChannel('paypal_checkout', 'paypal', 'paypal_checkout', PaymentFlow::REDIRECT);

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
                channel: $channel,
                credentials: [],
                params: [],
                runtimeParams: [],
                settings: $settings,
            ),
            'https://example.com/notify',
            'https://example.com/return',
            null,
            $client,
        );
    }
}
