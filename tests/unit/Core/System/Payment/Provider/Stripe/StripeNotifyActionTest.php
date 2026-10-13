<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeGatewayFactory;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotifyAction;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeNotifyAction::class)]
class StripeNotifyActionTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_123';

    public function testSupportsOnlyNotifyRequestsWithAnArrayObjectModel(): void
    {
        $action = new StripeNotifyAction();

        static::assertTrue($action->supports(new Notify(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Notify(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new StripeNotifyAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new \ArrayObject()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new StripeNotifyAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(StripeNotifyAction::class));

        $action->execute(new Notify(new \ArrayObject()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new StripeNotifyAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(StripeNotifyAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteRejectsANotificationWithoutASignatureHeader(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = new \ArrayObject([
            PaymentGatewayModel::RAW_BODY => $this->event(StripeNotifyAction::EVENT_PAYMENT_INTENT_SUCCEEDED, ['id' => 'pi_1']),
            PaymentGatewayModel::HEADERS => [],
        ]);

        $this->expectExceptionObject(PaymentException::notificationInvalid('stripe', 'the notification carries no stripe signature'));

        $action->execute(new Notify($model));
    }

    public function testExecuteRejectsANotificationWhenTheWebhookSecretIsMissing(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api([StripeApi::PUBLISHABLE_KEY => 'pk_test_123']));

        $model = $this->signedModel($this->event(StripeNotifyAction::EVENT_PAYMENT_INTENT_SUCCEEDED, ['id' => 'pi_1']));

        try {
            $action->execute(new Notify($model));
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::NOTIFICATION_INVALID, $exception->getErrorCode());

            return;
        }

        static::fail('The notification was expected to be rejected without a webhook secret.');
    }

    public function testExecuteReadsASucceededPaymentIntent(): void
    {
        $object = [
            'object' => 'payment_intent',
            'id' => 'pi_1',
            'status' => 'succeeded',
            'amount_received' => 1200,
            'currency' => 'cny',
            'metadata' => ['payment_no' => 'P-1'],
        ];

        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event(StripeNotifyAction::EVENT_PAYMENT_INTENT_SUCCEEDED, $object));

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertTrue($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('pi_1', $result->outPaymentNo);
        static::assertNull($result->outRefundId);
        static::assertSame(1200, $result->amount);
        static::assertSame('CNY', $result->currency);
        static::assertSame(StripeNotifyAction::EVENT_PAYMENT_INTENT_SUCCEEDED, $result->status);
        static::assertSame($object, $result->raw);
        static::assertSame($object, PaymentGatewayModel::responseOf($model));
    }

    public function testTheAmountReceivedWinsButFallsBackToTheAmount(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event(StripeNotifyAction::EVENT_PAYMENT_INTENT_SUCCEEDED, [
            'object' => 'payment_intent',
            'id' => 'pi_1',
            'status' => 'succeeded',
            'amount_received' => 0,
            'amount' => 900,
            'currency' => 'cny',
        ]));

        $action->execute(new Notify($model));

        static::assertSame(900, PaymentGatewayModel::notificationResult($model, 'stripe_checkout')->amount);
    }

    public function testExecuteReadsAPaidCheckoutSession(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event(StripeNotifyAction::EVENT_CHECKOUT_SESSION_COMPLETED, [
            'object' => 'checkout.session',
            'id' => 'cs_1',
            'payment_intent' => 'pi_2',
            'payment_status' => 'paid',
            'amount_total' => 1500,
            'currency' => 'usd',
        ]));

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertTrue($result->succeeded);
        static::assertSame('pi_2', $result->outPaymentNo);
        static::assertSame(1500, $result->amount);
        static::assertSame('USD', $result->currency);
    }

    public function testExecuteDoesNotConfirmAnUnpaidCheckoutSession(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event(StripeNotifyAction::EVENT_CHECKOUT_SESSION_COMPLETED, [
            'object' => 'checkout.session',
            'id' => 'cs_1',
            'payment_intent' => 'pi_2',
            'payment_status' => 'unpaid',
            'amount_total' => 1500,
            'currency' => 'usd',
        ]));

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertTrue($result->signatureValid);
        static::assertFalse($result->succeeded);
    }

    public function testAnExpandedPaymentIntentIsReadFromTheSession(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event(StripeNotifyAction::EVENT_CHECKOUT_SESSION_COMPLETED, [
            'object' => 'checkout.session',
            'id' => 'cs_1',
            'payment_intent' => ['id' => 'pi_3'],
            'payment_status' => 'paid',
            'currency' => 'usd',
        ]));

        $action->execute(new Notify($model));

        static::assertSame('pi_3', PaymentGatewayModel::notificationResult($model, 'stripe_checkout')->outPaymentNo);
    }

    public function testExecuteReportsAFailedPayment(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event('payment_intent.payment_failed', [
            'object' => 'payment_intent',
            'id' => 'pi_1',
            'status' => 'requires_payment_method',
            'amount' => 900,
            'currency' => 'cny',
        ]));

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertFalse($result->succeeded);
        static::assertSame('pi_1', $result->outPaymentNo);
        static::assertSame('payment_intent.payment_failed', $result->status);
    }

    public function testExecuteReadsASucceededRefund(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event('refund.created', [
            'object' => 'refund',
            'id' => 're_1',
            'status' => 'succeeded',
            'amount' => 500,
            'currency' => 'cny',
            'metadata' => ['refund_no' => 'R-1'],
        ]));

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertTrue($result->succeeded);
        static::assertSame('re_1', $result->outRefundId);
        static::assertNull($result->outPaymentNo);
        static::assertSame(500, $result->amount);
    }

    public function testExecuteDoesNotConfirmAFailedRefund(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event('refund.failed', [
            'object' => 'refund',
            'id' => 're_1',
            'status' => 'failed',
            'amount' => 500,
            'currency' => 'cny',
        ]));

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertFalse($result->succeeded);
        static::assertSame('re_1', $result->outRefundId);
    }

    public function testExecuteIgnoresAnEventItDoesNotKnow(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event('customer.created', [
            'object' => 'customer',
            'id' => 'cus_1',
            'currency' => 'cny',
        ]));

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertTrue($result->signatureValid);
        static::assertFalse($result->succeeded);
        static::assertSame('cus_1', $result->outPaymentNo);
    }

    public function testTheCurrencyDefaultsToUsd(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel($this->event('customer.created', ['object' => 'customer', 'id' => 'cus_1']));

        $action->execute(new Notify($model));

        static::assertSame('USD', PaymentGatewayModel::notificationResult($model, 'stripe_checkout')->currency);
    }

    public function testAWrongSignatureDoesNotConfirmTheNotification(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $rawBody = $this->event(StripeNotifyAction::EVENT_PAYMENT_INTENT_SUCCEEDED, [
            'object' => 'payment_intent',
            'id' => 'pi_1',
            'status' => 'succeeded',
            'currency' => 'cny',
        ]);

        $model = new \ArrayObject([
            PaymentGatewayModel::RAW_BODY => $rawBody,
            PaymentGatewayModel::HEADERS => ['stripe-signature' => 't=1700000000,v1=the-wrong-signature'],
        ]);

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertFalse($result->signatureValid);
        static::assertFalse($result->succeeded);
    }

    public function testASignatureHeaderWithoutATimestampDoesNotConfirmTheNotification(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = new \ArrayObject([
            PaymentGatewayModel::RAW_BODY => $this->event(StripeNotifyAction::EVENT_PAYMENT_INTENT_SUCCEEDED, ['id' => 'pi_1']),
            PaymentGatewayModel::HEADERS => ['stripe-signature' => 'v1=whatever'],
        ]);

        $action->execute(new Notify($model));

        static::assertFalse(PaymentGatewayModel::notificationResult($model, 'stripe_checkout')->signatureValid);
    }

    public function testTheSignatureHeaderMayBeWrittenWithUnderscores(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $rawBody = $this->event(StripeNotifyAction::EVENT_PAYMENT_INTENT_SUCCEEDED, [
            'object' => 'payment_intent',
            'id' => 'pi_1',
            'status' => 'succeeded',
            'currency' => 'cny',
        ]);

        $model = new \ArrayObject([
            PaymentGatewayModel::RAW_BODY => $rawBody,
            PaymentGatewayModel::HEADERS => ['stripe_signature' => $this->signatureHeader($rawBody)],
        ]);

        $action->execute(new Notify($model));

        static::assertTrue(PaymentGatewayModel::notificationResult($model, 'stripe_checkout')->signatureValid);
    }

    public function testAnUnparseableBodyIsAcknowledgedWithoutConfirming(): void
    {
        $action = new StripeNotifyAction();
        $action->setApi($this->api());

        $model = $this->signedModel('this is not json');

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, 'stripe_checkout');

        static::assertTrue($result->signatureValid);
        static::assertFalse($result->succeeded);
        static::assertNull($result->outPaymentNo);
        static::assertSame([], $result->raw);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function event(string $type, array $object): string
    {
        return (string) json_encode(['type' => $type, 'data' => ['object' => $object]]);
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    private function signedModel(string $rawBody): \ArrayObject
    {
        return new \ArrayObject([
            PaymentGatewayModel::RAW_BODY => $rawBody,
            PaymentGatewayModel::HEADERS => ['stripe-signature' => $this->signatureHeader($rawBody)],
        ]);
    }

    private function signatureHeader(string $rawBody): string
    {
        $timestamp = (new NativeClock())->now()->getTimestamp();

        return \sprintf(
            't=%d,v1=%s',
            $timestamp,
            hash_hmac('sha256', $timestamp . '.' . $rawBody, self::WEBHOOK_SECRET)
        );
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = [StripeApi::WEBHOOK_SECRET => self::WEBHOOK_SECRET]): StripeApi
    {
        return new StripeApi([
            AbstractPaymentApi::CONFIG_PROVIDER => StripeGatewayFactory::PROVIDER,
            AbstractPaymentApi::CONFIG_CHANNEL => 'stripe_checkout',
            AbstractPaymentApi::CONFIG_MODE => 'own',
            AbstractPaymentApi::CONFIG_CREDENTIALS => $credentials,
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => '',
        ]);
    }
}
