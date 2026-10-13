<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalRefundAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalRefundAction::class)]
class PaypalRefundActionTest extends TestCase
{
    public function testSupportsOnlyRefundRequestsWithAnArrayObjectModel(): void
    {
        $action = new PaypalRefundAction();

        static::assertTrue($action->supports(new Refund(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Refund(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalRefundAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(PaypalRefundAction::class));

        $action->execute(new Refund(new \ArrayObject()));
    }

    public function testExecuteThrowsWhenThePaymentHasNoCaptureYet(): void
    {
        $model = new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::AMOUNT => 25,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
        ]);

        $action = new PaypalRefundAction();
        $action->setApi($this->api());

        $this->expectExceptionObject(PaymentException::gatewayError(
            'paypal_checkout',
            'the payment has no capture at paypal yet and therefore cannot be refunded'
        ));

        $action->execute(new Refund($model));
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheRefundRequest(): void
    {
        $model = new \ArrayObject([
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
        ], PaymentGatewayModel::requestOf($model));
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
