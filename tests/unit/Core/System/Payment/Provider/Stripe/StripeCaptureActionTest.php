<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeCaptureAction;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeCaptureAction::class)]
class StripeCaptureActionTest extends TestCase
{
    public function testSupportsOnlyCaptureRequestsWithAnArrayObjectModel(): void
    {
        $action = new StripeCaptureAction();

        static::assertTrue($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Refund(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new StripeCaptureAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Refund(new \ArrayObject()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new StripeCaptureAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(StripeCaptureAction::class));

        $action->execute(new Capture(new \ArrayObject()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new StripeCaptureAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(StripeCaptureAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testTheDefaultFlowIsTheHostedCheckoutRedirect(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api());

        $model = $this->orderModel();

        $exception = $this->captureExpectingFailure($action, $model);

        static::assertStringContainsString('secret_key', $exception->getMessage());

        $request = PaymentGatewayModel::requestOf($model);
        static::assertSame('redirect', $request['flow']);
        static::assertSame(1234, $request['amount']);
        static::assertSame('cny', $request['currency']);
        static::assertSame([StripeNotificationExtractor::METADATA_PAYMENT_NO => 'P-1'], $request['metadata']);

        static::assertArrayHasKey('error', PaymentGatewayModel::responseOf($model));
    }

    public function testTheSdkFlowIsSelectedByTheSetting(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api(settings: [StripeCaptureAction::SETTING_FLOW => StripeCaptureAction::FLOW_SDK]));

        $model = $this->orderModel();

        $this->captureExpectingFailure($action, $model);

        static::assertSame('sdk', PaymentGatewayModel::requestOf($model)['flow']);
    }

    public function testTheFlowSettingIsTrimmedAndLowercased(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api(settings: [StripeCaptureAction::SETTING_FLOW => ' SDK ']));

        $model = $this->orderModel();

        $this->captureExpectingFailure($action, $model);

        static::assertSame('sdk', PaymentGatewayModel::requestOf($model)['flow']);
    }

    public function testAnUnknownFlowOpensTheCheckoutSession(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api(settings: [StripeCaptureAction::SETTING_FLOW => 'unknown']));

        $model = $this->orderModel();

        $this->captureExpectingFailure($action, $model);

        // the flow is recorded verbatim, but anything but `sdk` falls back to the hosted checkout
        static::assertSame('unknown', PaymentGatewayModel::requestOf($model)['flow']);
    }

    public function testTheOutTradeNoIsAddedToTheMetadata(): void
    {
        $action = new StripeCaptureAction();
        $action->setApi($this->api());

        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::OUT_TRADE_NO, 'T-1');

        $this->captureExpectingFailure($action, $model);

        static::assertSame(
            [
                StripeNotificationExtractor::METADATA_PAYMENT_NO => 'P-1',
                StripeNotificationExtractor::METADATA_OUT_TRADE_NO => 'T-1',
            ],
            PaymentGatewayModel::requestOf($model)['metadata']
        );
    }

    public function testAnExpiryIsEvaluatedAgainstTheClock(): void
    {
        $clock = new MockClock('2024-01-01T00:00:00+00:00');

        $action = new StripeCaptureAction($clock);
        $action->setApi($this->api());

        $model = $this->orderModel();
        $model->offsetSet(PaymentGatewayModel::EXPIRE_AT, $clock->now()->modify('+2 hours'));

        $this->captureExpectingFailure($action, $model);

        static::assertSame('redirect', PaymentGatewayModel::requestOf($model)['flow']);
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    private function captureExpectingFailure(StripeCaptureAction $action, \ArrayObject $model): PaymentException
    {
        try {
            $action->execute(new Capture($model));
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());

            return $exception;
        }

        static::fail('The capture was expected to fail without stripe credentials.');
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    private function orderModel(): \ArrayObject
    {
        return new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 1234,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'Order subject',
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $settings
     */
    private function api(array $credentials = [], array $settings = []): StripeApi
    {
        return new StripeApi([
            AbstractPaymentApi::CONFIG_PROVIDER => 'stripe',
            AbstractPaymentApi::CONFIG_CHANNEL => 'stripe_checkout',
            AbstractPaymentApi::CONFIG_MODE => 'own',
            AbstractPaymentApi::CONFIG_CREDENTIALS => $credentials,
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => $settings,
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => 'https://example.com/return',
        ]);
    }
}
