<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalCaptureAction;
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

    private function api(): PaypalApi
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
                settings: [PaypalCaptureAction::SETTING_FLOW => PaypalCaptureAction::FLOW_REDIRECT],
            ),
            'https://example.com/notify',
            'https://example.com/return',
        );
    }
}
