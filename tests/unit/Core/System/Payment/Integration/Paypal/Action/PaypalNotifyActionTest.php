<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Paypal\Action;

use PaypalServerSdkLib\Controllers\OrdersController;
use PaypalServerSdkLib\Http\ApiResponse;
use PaypalServerSdkLib\PaypalServerSdkClient;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentNotificationException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalNotifyAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Api\PaypalApi;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalNotifyAction::class)]
class PaypalNotifyActionTest extends TestCase
{
    public function testVerifiedApprovalCapturesTheSameOrderWithAStableRequestId(): void
    {
        [$model, $httpClient] = $this->approvedModel('PG-1');
        $response = static::createStub(ApiResponse::class);
        $response->method('getBody')->willReturn(json_encode([
            'id' => 'ORDER-1',
            'purchase_units' => [['payments' => ['captures' => [[
                'id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['value' => '10.00', 'currency_code' => 'USD'],
            ]]]]],
        ], \JSON_THROW_ON_ERROR));
        $orders = static::createMock(OrdersController::class);
        $orders->expects($this->exactly(2))->method('captureOrder')->with([
            'id' => 'ORDER-1', 'paypalRequestId' => 'PG-1:capture', 'prefer' => 'return=representation',
        ])->willReturn($response);
        $client = static::createStub(PaypalServerSdkClient::class);
        $client->method('getOrdersController')->willReturn($orders);
        $action = new PaypalNotifyAction();
        $action->setApi($this->api($client, $httpClient));

        $action->execute(new Notify($model));
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'paypal_checkout');
        static::assertTrue($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('PG-1', $result->paymentNo);
        static::assertSame('CAP-1', $result->outPaymentNo);
        static::assertSame(1000, $result->amount);
    }

    public function testVerifiedApprovalOfAnotherPaymentCannotCapture(): void
    {
        [$model, $httpClient] = $this->approvedModel('PG-2');
        $orders = static::createMock(OrdersController::class);
        $orders->expects($this->never())->method('captureOrder');
        $client = static::createStub(PaypalServerSdkClient::class);
        $client->method('getOrdersController')->willReturn($orders);
        $action = new PaypalNotifyAction();
        $action->setApi($this->api($client, $httpClient));
        $this->expectExceptionObject(PaymentNotificationException::notificationInvalid('paypal', 'the signed approval does not match the payment'));

        $action->execute(new Notify($model));
    }

    public function testUnsignedApprovalCannotCapture(): void
    {
        [$model, $httpClient] = $this->approvedModel('PG-1');
        $model->offsetSet(PaymentGatewayModel::HEADERS, []);
        $orders = static::createMock(OrdersController::class);
        $orders->expects($this->never())->method('captureOrder');
        $client = static::createStub(PaypalServerSdkClient::class);
        $client->method('getOrdersController')->willReturn($orders);
        $action = new PaypalNotifyAction();
        $action->setApi($this->api($client, $httpClient));

        $action->execute(new Notify($model));

        static::assertFalse($model->result(NotificationResult::class, 'paypal_checkout')->signatureValid);
    }

    public function testSupportsOnlyNotifyRequestsWithAPaymentGatewayModel(): void
    {
        $action = new PaypalNotifyAction();

        static::assertTrue($action->supports(new Notify(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Notify(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalNotifyAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(PaypalNotifyAction::class));

        $action->execute(new Notify(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWhenTheNotificationIsNotJson(): void
    {
        $this->expectNotificationInvalid('not-json');
    }

    public function testAVerifiedCaptureNotificationIsASuccess(): void
    {
        $resource = ['id' => 'CAP-1', 'amount' => ['value' => '10.00', 'currency_code' => 'USD']];
        $event = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => $resource];

        $result = PaypalNotifyAction::resultOf($event, true);

        static::assertTrue($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('CAP-1', $result->outPaymentNo);
        static::assertNull($result->outRefundId);
        static::assertSame(1000, $result->amount);
        static::assertSame('USD', $result->currency);
        static::assertSame('PAYMENT.CAPTURE.COMPLETED', $result->status);
        static::assertSame($resource, $result->raw);

        // the very same claim without a valid signature is not a success
        static::assertFalse(PaypalNotifyAction::resultOf($event, false)->succeeded);
    }

    public function testExecuteMarksADeniedCaptureAsNotSucceeded(): void
    {
        $model = $this->model(['event_type' => 'PAYMENT.CAPTURE.DENIED', 'resource' => ['id' => 'CAP-1']]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'paypal_checkout');
        static::assertFalse($result->succeeded);
        static::assertFalse($result->signatureValid);
        static::assertSame('PAYMENT.CAPTURE.DENIED', $result->status);
        static::assertNull($result->outRefundId);
    }

    public function testAVerifiedRefundNotificationIsASuccess(): void
    {
        $result = PaypalNotifyAction::resultOf([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => ['id' => 'CAP-1', 'amount' => ['value' => '10.00', 'currency_code' => 'USD']],
        ], true);

        static::assertTrue($result->succeeded);
        static::assertSame('CAP-1', $result->outRefundId);
        static::assertSame('CAP-1', $result->outPaymentNo);
        static::assertSame(1000, $result->amount);
    }

    public function testExecuteTreatsAnEventWithARefundMarkerAsARefund(): void
    {
        $model = $this->model(['event_type' => 'CUSTOM.REFUND.COMPLETED', 'resource' => ['id' => 'CAP-1']]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'paypal_checkout');
        static::assertSame('CAP-1', $result->outRefundId);
        static::assertFalse($result->succeeded);
    }

    public function testExecuteTreatsAnUnknownEventAsNotSucceeded(): void
    {
        $model = $this->model(['event_type' => 'PAYMENT.CAPTURE.PENDING', 'resource' => ['id' => 'CAP-1']]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'paypal_checkout');
        static::assertFalse($result->succeeded);
        static::assertNull($result->outRefundId);
        static::assertSame('PAYMENT.CAPTURE.PENDING', $result->status);
    }

    public function testExecuteFallsBackToTheDefaultCurrency(): void
    {
        $model = $this->model([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-1', 'amount' => ['value' => '5.00']],
        ]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'paypal_checkout');
        static::assertSame('USD', $result->currency);
        static::assertSame(500, $result->amount);
    }

    public function testExecuteReadsTheAmountFromTheResourceWhenThereIsNoAmountObject(): void
    {
        $model = $this->model([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-1', 'value' => '7.00'],
        ]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'paypal_checkout');
        static::assertSame(700, $result->amount);
        static::assertSame('USD', $result->currency);
    }

    public function testExecuteIgnoresAnAmountThatCannotBeParsed(): void
    {
        $model = $this->model([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-1', 'amount' => ['value' => 'not-a-number', 'currency_code' => 'USD']],
        ]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'paypal_checkout');
        static::assertNull($result->amount);
    }

    public function testExecuteKeepsGoingWhenTheBodyCarriesNoResource(): void
    {
        $model = $this->model(['event_type' => 'PAYMENT.CAPTURE.COMPLETED']);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, 'paypal_checkout');
        static::assertNull($result->outPaymentNo);
        static::assertSame([], $result->raw);
        // the event claims COMPLETED, but the signature was never verified - so it is not a success
        static::assertFalse($result->succeeded);
    }

    private function expectNotificationInvalid(string $rawBody): void
    {
        $action = new PaypalNotifyAction();
        $action->setApi($this->api());

        try {
            $action->execute(new Notify(new PaymentGatewayModel([PaymentGatewayModel::RAW_BODY => $rawBody, PaymentGatewayModel::HEADERS => []])));
            static::fail('The notification was expected to be rejected.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::NOTIFICATION_INVALID, $exception->getErrorCode());
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private function model(array $event): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
            PaymentGatewayModel::RAW_BODY => json_encode($event, \JSON_THROW_ON_ERROR),
            PaymentGatewayModel::HEADERS => [],
        ]);
    }

    /**
     * @return array{PaymentGatewayModel, HttpClientInterface}
     */
    private function approvedModel(string $paymentNo): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($key);
        $details = openssl_pkey_get_details($key);
        static::assertNotFalse($details);
        $rawBody = json_encode([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'ORDER-1', 'purchase_units' => [['custom_id' => $paymentNo]]],
        ], \JSON_THROW_ON_ERROR);
        $signature = '';
        static::assertTrue(openssl_sign('transmission|2026-11-05T00:00:00Z|WH-1|' . crc32($rawBody), $signature, $key, \OPENSSL_ALGO_SHA256));
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'PG-1',
            PaymentGatewayModel::RAW_BODY => $rawBody,
            PaymentGatewayModel::HEADERS => [
                PaypalApi::HEADER_TRANSMISSION_ID => 'transmission',
                PaypalApi::HEADER_TRANSMISSION_TIME => '2026-11-05T00:00:00Z',
                PaypalApi::HEADER_TRANSMISSION_SIG => base64_encode($signature),
                PaypalApi::HEADER_CERT_URL => 'https://api.paypal.com/v1/notifications/certs/CERT-1',
                PaypalApi::HEADER_AUTH_ALGO => 'SHA256WITHRSA',
            ],
        ]);

        return [$model, new MockHttpClient(new MockResponse($details['key']))];
    }

    private function api(?PaypalServerSdkClient $client = null, ?HttpClientInterface $httpClient = null): PaypalApi
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
                credentials: [PaypalApi::WEBHOOK_ID => 'WH-1'],
                params: [],
                runtimeParams: [],
                settings: [],
                accountId: 'test-account',
            ),
            'https://example.com/notify',
            'https://example.com/return',
            $httpClient,
            $client,
        );
    }
}
