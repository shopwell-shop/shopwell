<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalQueryAction;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalQueryAction::class)]
class PaypalQueryActionTest extends TestCase
{
    public function testSupportsOnlyQueryRequestsWithAPaymentGatewayModel(): void
    {
        $action = new PaypalQueryAction();

        static::assertTrue($action->supports(new PaymentQuery(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new PaymentQuery(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalQueryAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(PaypalQueryAction::class));

        $action->execute(new PaymentQuery(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new PaypalQueryAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(PaypalQueryAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteAnswersAbsentWhenTheCaptureDidNotHandOutAnOrderId(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::LAST_CHANNEL_RESPONSE => ['status' => 'created'],
        ]);

        $action = new PaypalQueryAction();
        $action->setApi($this->api());

        $action->execute(new PaymentQuery($model));

        $request = $model->request();
        static::assertNull($request['order_id']);

        $result = $model->result(QueryResult::class, 'paypal_checkout');
        static::assertSame(QueryResult::STATUS_ABSENT, $result->status);
    }

    public function testExecuteAsksForTheOrderOfTheLastCaptureAndWrapsAGatewayFailure(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::LAST_CHANNEL_RESPONSE => ['id' => 'PAYPAL-ORDER-1'],
        ]);

        $action = new PaypalQueryAction();
        // without the client credentials the sdk fails while it is built, before it would talk to paypal
        $action->setApi($this->api());

        try {
            $action->execute(new PaymentQuery($model));
            static::fail('The query was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('PAYPAL-ORDER-1', $request['order_id']);

        static::assertArrayHasKey('error', $model->response());
    }

    private function api(): PaypalApi
    {
        $channel = new PaymentChannel('paypal_checkout', 'paypal', 'Paypal Checkout', PaymentFlow::REDIRECT);

        return new PaypalApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'paypal',
                    'Paypal',
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
            '',
        );
    }
}
