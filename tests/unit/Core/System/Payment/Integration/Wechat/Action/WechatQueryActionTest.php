<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Wechat\Action;

use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentQuery;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatQueryAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Integration\Wechat\Product\WechatProducts;
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
#[CoversClass(WechatQueryAction::class)]
class WechatQueryActionTest extends TestCase
{
    public function testSupportsOnlyQueryRequestsWithAPaymentGatewayModel(): void
    {
        $action = new WechatQueryAction();

        static::assertTrue($action->supports(new PaymentQuery(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new PaymentQuery(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatQueryAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(WechatQueryAction::class));

        $action->execute(new PaymentQuery(new PaymentGatewayModel()));
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheQueryRequest(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
        ]);

        $action = new WechatQueryAction();
        // the merchant certificate is incomplete, so the sdk fails before it would talk to the channel
        $action->setApi($this->api());

        try {
            $action->execute(new PaymentQuery($model));
            static::fail('The query was expected to fail without a complete merchant certificate.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('ORDER-1', $request['out_trade_no']);

        static::assertArrayHasKey('error', $model->response());
    }

    private function api(): WechatApi
    {
        $channel = new PaymentProduct(WechatProducts::H5, 'wechat', WechatProducts::H5, [PaymentInteraction::REDIRECT], 'wechat');

        return new WechatApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'wechat',
                    'wechat',
                    [],
                    [WechatProducts::H5 => $channel],
                    new PaymentGatewayFactory('wechat', WechatApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                product: $channel,
                credentials: [WechatApi::MCH_ID => '1900000001'],
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
