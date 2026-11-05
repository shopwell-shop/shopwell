<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Alipay\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentClose;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentQuery;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCloseAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\Integration\Alipay\Product\AlipayProducts;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayCloseAction::class)]
class AlipayCloseActionTest extends TestCase
{
    public function testSupportsOnlyCloseRequestsWithAPaymentGatewayModel(): void
    {
        $action = new AlipayCloseAction();

        static::assertTrue($action->supports(new PaymentClose(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new PaymentQuery(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new PaymentClose(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayCloseAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayCloseAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(AlipayCloseAction::class));

        $action->execute(new PaymentClose(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new AlipayCloseAction();

        $this->expectExceptionObject(PaymentGatewayException::unsupportedApi(AlipayCloseAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteClosesByTheMerchantOrderNumberAndWrapsAGatewayFailure(): void
    {
        $action = new AlipayCloseAction();
        // without credentials the sdk fails while it is built, before it would talk to the channel
        $action->setApi($this->api());

        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::CURRENCY => 'CNY',
        ]);

        try {
            $action->execute(new PaymentClose($model));
            static::fail('The close was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame(AlipayCloseAction::METHOD_CLOSE, $request['method']);
        static::assertSame('P-1', $request['out_trade_no']);

        static::assertArrayHasKey('error', $model->response());
    }

    private function api(): AlipayApi
    {
        $channel = new PaymentProduct(AlipayProducts::H5, 'alipay', AlipayProducts::H5, [PaymentInteraction::REDIRECT], 'alipay');

        return new AlipayApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'alipay',
                    'alipay',
                    [],
                    [AlipayProducts::H5 => $channel],
                    new PaymentGatewayFactory('alipay', AlipayApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                product: $channel,
                credentials: [],
                params: [],
                runtimeParams: [],
                settings: [],
                accountId: 'test-account',
            ),
            'https://example.com/notify',
            '',
        );
    }
}
