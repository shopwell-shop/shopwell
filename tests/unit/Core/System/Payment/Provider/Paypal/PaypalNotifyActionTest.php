<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotifyAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalNotifyAction::class)]
class PaypalNotifyActionTest extends TestCase
{
    public function testSupportsOnlyNotifyRequestsWithAnArrayObjectModel(): void
    {
        $action = new PaypalNotifyAction();

        static::assertTrue($action->supports(new Notify(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Notify(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalNotifyAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(PaypalNotifyAction::class));

        $action->execute(new Notify(new \ArrayObject()));
    }

    public function testExecuteThrowsWhenTheNotificationIsNotJson(): void
    {
        $this->expectNotificationInvalid('not-json');
    }

    public function testExecuteReadsASuccessfulCaptureNotification(): void
    {
        $resource = ['id' => 'CAP-1', 'amount' => ['value' => '10.00', 'currency_code' => 'USD']];
        $event = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => $resource];

        $model = $this->model($event);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
        static::assertFalse($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('CAP-1', $result->outPaymentNo);
        static::assertNull($result->outRefundId);
        static::assertSame(1000, $result->amount);
        static::assertSame('USD', $result->currency);
        static::assertSame('PAYMENT.CAPTURE.COMPLETED', $result->status);
        static::assertSame($resource, $result->raw);
        static::assertSame($event, PaymentGatewayModel::responseOf($model));
    }

    public function testExecuteMarksADeniedCaptureAsNotSucceeded(): void
    {
        $model = $this->model(['event_type' => 'PAYMENT.CAPTURE.DENIED', 'resource' => ['id' => 'CAP-1']]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
        static::assertFalse($result->succeeded);
        static::assertFalse($result->signatureValid);
        static::assertSame('PAYMENT.CAPTURE.DENIED', $result->status);
        static::assertNull($result->outRefundId);
    }

    public function testExecuteReadsARefundNotification(): void
    {
        $model = $this->model([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => ['id' => 'CAP-1', 'amount' => ['value' => '10.00', 'currency_code' => 'USD']],
        ]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
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

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
        static::assertSame('CAP-1', $result->outRefundId);
        static::assertFalse($result->succeeded);
    }

    public function testExecuteTreatsAnUnknownEventAsNotSucceeded(): void
    {
        $model = $this->model(['event_type' => 'PAYMENT.CAPTURE.PENDING', 'resource' => ['id' => 'CAP-1']]);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
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

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
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

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
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

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
        static::assertNull($result->amount);
    }

    public function testExecuteKeepsGoingWhenTheBodyCarriesNoResource(): void
    {
        $model = $this->model(['event_type' => 'PAYMENT.CAPTURE.COMPLETED']);

        $action = new PaypalNotifyAction();
        $action->setApi($this->api());
        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'paypal_checkout');
        static::assertNull($result->outPaymentNo);
        static::assertSame([], $result->raw);
        static::assertTrue($result->succeeded);
    }

    private function expectNotificationInvalid(string $rawBody): void
    {
        $action = new PaypalNotifyAction();
        $action->setApi($this->api());

        try {
            $action->execute(new Notify(new \ArrayObject([PaymentGatewayModel::RAW_BODY => $rawBody, PaymentGatewayModel::HEADERS => []])));
            static::fail('The notification was expected to be rejected.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::NOTIFICATION_INVALID, $exception->getErrorCode());
        }
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return \ArrayObject<string, mixed>
     */
    private function model(array $event): \ArrayObject
    {
        return new \ArrayObject([
            PaymentGatewayModel::RAW_BODY => json_encode($event, \JSON_THROW_ON_ERROR),
            PaymentGatewayModel::HEADERS => [],
        ]);
    }

    private function api(): PaypalApi
    {
        return new PaypalApi([
            AbstractPaymentApi::CONFIG_PROVIDER => 'paypal',
            AbstractPaymentApi::CONFIG_CHANNEL => 'paypal_checkout',
            AbstractPaymentApi::CONFIG_MODE => 'live',
            AbstractPaymentApi::CONFIG_CREDENTIALS => [],
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => 'https://example.com/return',
        ]);
    }
}
