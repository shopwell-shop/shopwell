<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalCaptureAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalCaptureAction::class)]
class PaypalCaptureActionTest extends TestCase
{
    public function testSupportsOnlyCaptureRequestsWithAnArrayObjectModel(): void
    {
        $action = new PaypalCaptureAction();

        static::assertTrue($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Refund(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalCaptureAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(PaypalCaptureAction::class));

        $action->execute(new Capture(new \ArrayObject()));
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheOrderRequest(): void
    {
        $model = new \ArrayObject([
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
        ], PaymentGatewayModel::requestOf($model));
    }

    public function testExecuteUppercasesTheCurrencyOfTheOrder(): void
    {
        $model = new \ArrayObject([
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

        $request = PaymentGatewayModel::requestOf($model);
        static::assertSame('USD', $request['currency']);
        static::assertSame('0.05', $request['amount']);
    }

    private function api(): PaypalApi
    {
        return new PaypalApi([
            AbstractPaymentApi::CONFIG_PROVIDER => 'paypal',
            AbstractPaymentApi::CONFIG_CHANNEL => 'paypal_checkout',
            AbstractPaymentApi::CONFIG_MODE => 'live',
            AbstractPaymentApi::CONFIG_CREDENTIALS => [],
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [PaypalCaptureAction::SETTING_FLOW => PaypalCaptureAction::FLOW_REDIRECT],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => 'https://example.com/return',
        ]);
    }
}
