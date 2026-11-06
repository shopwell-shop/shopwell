<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Wechat\Action;

use GuzzleHttp\HandlerStack;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentRefundRejectedException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatRefundAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Integration\Wechat\Product\WechatProducts;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Tests\Unit\Core\System\Payment\Integration\Wechat\FakeWechatHandler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatRefundAction::class)]
class WechatRefundActionTest extends TestCase
{
    public function testQueriesAnAcceptedRefundWithoutSubmittingAgain(): void
    {
        $action = $this->queryAction(['status' => 'SUCCESS', 'refund_id' => 'remote-refund']);
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::OUT_PAYMENT_NO => 'remote-payment',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
        ]);

        $action->execute(new RefundQuery($model, 'remote-refund'));

        $result = $model->result(RefundResult::class, 'channel');
        static::assertSame(PaymentRefundStates::STATE_FINISHED, $result->status);
        static::assertSame('remote-refund', $result->outRefundId);
    }

    public function testAnUnrecognizedQueryAnswerKeepsTheRefundReserved(): void
    {
        $action = $this->queryAction([]);
        $model = new PaymentGatewayModel();

        $action->execute(new RefundQuery($model, 'remote-refund'));

        $result = $model->result(RefundResult::class, 'channel');
        static::assertSame(PaymentRefundStates::STATE_PROCESSING, $result->status);
        static::assertSame('remote-refund', $result->outRefundId);
    }

    public function testAConfirmedFailedQueryReleasesTheReservation(): void
    {
        $action = $this->queryAction(['status' => 'CLOSED']);

        $this->expectException(PaymentRefundRejectedException::class);

        $action->execute(new RefundQuery(new PaymentGatewayModel(), 'remote-refund'));
    }

    public function testAnAbnormalRefundKeepsItsAmountReservedForMerchantHandling(): void
    {
        $action = $this->queryAction(['status' => 'ABNORMAL', 'refund_id' => 'remote-refund']);
        $model = new PaymentGatewayModel();

        $action->execute(new RefundQuery($model, 'remote-refund'));

        static::assertSame(PaymentRefundStates::STATE_PROCESSING, $model->result(RefundResult::class, 'wechat')->status);
    }

    public function testSupportsOnlyRefundRequestsWithAPaymentGatewayModel(): void
    {
        $action = new WechatRefundAction();

        static::assertTrue($action->supports(new Refund(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Refund(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatRefundAction();

        $this->expectExceptionObject(PaymentGatewayException::actionWithoutApi(WechatRefundAction::class));

        $action->execute(new Refund(new PaymentGatewayModel()));
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheRefundRequest(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::AMOUNT => 25,
            PaymentGatewayModel::ORDER_AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
            PaymentGatewayModel::REASON => 'customer asked',
        ]);

        $action = new WechatRefundAction();
        // the merchant certificate is incomplete, so the sdk fails before it would talk to the channel
        $action->setApi($this->api());

        try {
            $action->execute(new Refund($model));
            static::fail('The refund was expected to fail without a complete merchant certificate.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = $model->request();
        static::assertSame('ORDER-1', $request['out_trade_no']);
        static::assertSame('REFUND-1', $request['out_refund_no']);
        static::assertSame('customer asked', $request['reason']);
        static::assertSame('https://example.com/notify', $request['notify_url']);
        static::assertSame(['refund' => 25, 'total' => 100, 'currency' => 'CNY'], $request['amount']);

        static::assertArrayHasKey('error', $model->response());
    }

    public function testExecuteLeavesTheReasonOutWhenItIsMissing(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::AMOUNT => 25,
            PaymentGatewayModel::ORDER_AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
        ]);

        $action = new WechatRefundAction();
        $action->setApi($this->api());

        try {
            $action->execute(new Refund($model));
            static::fail('The refund was expected to fail without a complete merchant certificate.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        static::assertArrayNotHasKey('reason', $model->request());
    }

    #[DataProvider('refundUris')]
    public function testTheRefundUriIsStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function refundUris(): \Generator
    {
        yield 'refund' => ['v3/refund/domestic/refunds', WechatRefundAction::URI_REFUND];
    }

    /**
     * @param array<string, mixed> $answer
     */
    private function queryAction(array $answer): WechatRefundAction
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        static::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        static::assertIsArray($details);
        $handler = new FakeWechatHandler($privateKey, 'PLATFORM-SERIAL');
        $handler->answer(200, $answer);
        $action = new WechatRefundAction();
        $action->setApi($this->api($handler->stack(), [
            WechatApi::MCH_ID => '1900000001',
            WechatApi::MCH_SERIAL => 'MCH-SERIAL',
            WechatApi::MCH_PRIVATE_KEY => $privateKey,
            WechatApi::PLATFORM_CERT => $details['key'],
            WechatApi::PLATFORM_SERIAL => 'PLATFORM-SERIAL',
        ]));

        return $action;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(?HandlerStack $handler = null, array $credentials = [WechatApi::MCH_ID => '1900000001']): WechatApi
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
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: [],
                accountId: 'test-account',
            ),
            'https://example.com/notify',
            '',
            $handler,
        );
    }
}
