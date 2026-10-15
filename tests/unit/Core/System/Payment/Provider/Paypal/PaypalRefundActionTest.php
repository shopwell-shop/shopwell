<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

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
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalRefundAction;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalRefundAction::class)]
class PaypalRefundActionTest extends TestCase
{
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

        $this->expectExceptionObject(PaymentException::actionWithoutApi(PaypalRefundAction::class));

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

        $this->expectExceptionObject(PaymentException::gatewayError(
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
                settings: [],
                mode: 'live',
            ),
            'https://example.com/notify',
            'https://example.com/return',
        );
    }
}
