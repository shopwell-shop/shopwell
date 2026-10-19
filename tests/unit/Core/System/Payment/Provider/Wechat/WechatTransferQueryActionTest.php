<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferQueryAction;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatTransferQueryAction::class)]
class WechatTransferQueryActionTest extends TestCase
{
    private const PLATFORM_SERIAL = 'PLATFORM-SERIAL';

    /**
     * The out number carries upper case letters on purpose: the sdk normalizes a chain segment to
     * kebab case, which would ask for a bill nobody opened - the path must stay byte for byte.
     */
    private const QUERY_PATH = '/v3/fund-app/mch-transfer/transfer-bills/out-bill-no/AbC123';

    /**
     * @var array{merchant: array{0: string, 1: string}, platform: array{0: string, 1: string}}|null
     */
    private static ?array $keys = null;

    private FakeWechatHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new FakeWechatHandler($this->platformPrivateKey(), self::PLATFORM_SERIAL);
    }

    public function testSupportsOnlyTransferQueriesWithAPaymentGatewayModel(): void
    {
        $action = new WechatTransferQueryAction();

        static::assertTrue($action->supports($this->request()));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
    }

    public function testSupportsRejectsARequestWhoseModelIsNotAPaymentGatewayModel(): void
    {
        $request = $this->request();
        $request->setModel(new \stdClass());

        static::assertFalse((new WechatTransferQueryAction())->supports($request));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new WechatTransferQueryAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatTransferQueryAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(WechatTransferQueryAction::class));

        $action->execute($this->request());
    }

    /**
     * A transfer whose submission never opened a bill has nothing wechat could be asked about - and a
     * query that cannot be answered must not cost a call.
     */
    public function testATransferWithoutAProviderReferenceIsAnsweredUnknownWithoutACall(): void
    {
        $action = new WechatTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request(providerRef: null);
        $action->execute($request);

        static::assertSame([], $this->handler->requests);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
        static::assertSame('', $result->providerStatus);
    }

    public function testTheQueryIsAddressedToTheBillPath(): void
    {
        $this->handler->answer(200, ['state' => 'PROCESSING', 'transfer_bill_no' => 'BILL-1']);

        $action = new WechatTransferQueryAction();
        $action->setApi($this->api());

        $action->execute($this->request());

        $call = $this->handler->only();

        static::assertSame('GET', $call['method']);
        static::assertSame(self::QUERY_PATH, $call['path']);
    }

    /**
     * The one time `package_info` is issued once - a client that lost the answer of the submission can
     * only get it from here, so the query may not drop it.
     */
    public function testTheQueryCarriesTheConfirmationPayloadAgain(): void
    {
        $package = str_repeat('package-info-with-a-long-body-', 10);

        $this->handler->answer(200, [
            'state' => 'WAIT_USER_CONFIRM',
            'transfer_bill_no' => 'BILL-1',
            'package_info' => $package,
        ]);

        $action = new WechatTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_AWAITING_CONFIRM, $result->state);
        static::assertSame('WAIT_USER_CONFIRM', $result->providerStatus);
        static::assertSame('BILL-1', $result->providerRef);
        static::assertSame($package, $result->confirmPayload);
    }

    /**
     * Wechat answers for the last 30 days only; an older bill is a 404 and that is no channel failure.
     */
    public function testABillWechatDoesNotKnowStaysUnknown(): void
    {
        $this->handler->answer(404, ['code' => 'NOT_FOUND', 'message' => 'the bill does not exist']);

        $action = new WechatTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
        static::assertSame(WechatApi::TRANSFER_BILL_NOT_FOUND, $result->providerStatus);
        static::assertNull($result->failureCode);
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function billStates(): iterable
    {
        yield 'success arrived' => ['SUCCESS', TransferResult::STATE_SUCCEEDED, null];
        yield 'wait for confirm still waits' => ['WAIT_USER_CONFIRM', TransferResult::STATE_AWAITING_CONFIRM, null];
        yield 'accepted is on its way' => ['ACCEPTED', TransferResult::STATE_PROCESSING, null];
        // the books: a cancelled bill never moved money, so it is no return
        yield 'cancelled is a failure' => ['CANCELLED', TransferResult::STATE_FAILED, 'CANCELLED'];
        yield 'fail names itself' => ['FAIL', TransferResult::STATE_FAILED, 'FAIL'];
        yield 'a new state stays unknown' => ['SOMETHING_NEW', TransferResult::STATE_UNKNOWN, null];
    }

    #[DataProvider('billStates')]
    public function testTheQueryMapsTheBillStateLikeTheSubmission(
        string $state,
        string $expectedState,
        ?string $expectedFailureCode,
    ): void {
        $result = WechatTransferQueryAction::resultOf([
            'state' => $state,
            'transfer_bill_no' => 'BILL-1',
        ]);

        static::assertSame($expectedState, $result->state);
        static::assertSame($state, $result->providerStatus);
        static::assertSame('BILL-1', $result->providerRef);
        static::assertSame($expectedFailureCode, $result->failureCode);
    }

    public function testAChannelErrorIsWrappedAndKeepsTheEvidence(): void
    {
        $this->handler->answer(500, ['code' => 'SYSTEM_ERROR', 'message' => 'the channel is busy']);

        $action = new WechatTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();

        try {
            $action->execute($request);
            static::fail('The query was expected to fail on a channel error.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        static::assertSame('AbC123', $model->request()['out_bill_no']);
        static::assertArrayHasKey('error', $model->response());
    }

    private function platformPrivateKey(): string
    {
        return self::keys()['platform'][0];
    }

    private function platformPublicKey(): string
    {
        return self::keys()['platform'][1];
    }

    private function merchantPrivateKey(): string
    {
        return self::keys()['merchant'][0];
    }

    /**
     * @return array{merchant: array{0: string, 1: string}, platform: array{0: string, 1: string}}
     */
    private static function keys(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }

        self::$keys = ['merchant' => self::keyPair(), 'platform' => self::keyPair()];

        return self::$keys;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function keyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        static::assertIsArray($details);

        return [(string) $privateKey, (string) $details['key']];
    }

    private function request(?string $providerRef = 'AbC123'): PaymentTransferQuery
    {
        $transfer = new PaymentTransferEntity();
        $transfer->outNo = 'OUT-1';
        $transfer->provider = 'wechat';
        $transfer->channel = 'wechat_h5';
        $transfer->amount = 1000;
        $transfer->currency = 'CNY';
        $transfer->providerRef = $providerRef;

        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'rec-1';
        $recipient->provider = 'wechat';
        $recipient->identityType = 'wechat_open_id';
        $recipient->identityValue = 'irrelevant-for-a-query';
        $recipient->onboardingStatus = 'registered';
        $recipient->payoutEnabled = true;

        return new PaymentTransferQuery(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $recipient,
        );
    }

    private function resultOf(PaymentTransferQuery $request): TransferQueryResult
    {
        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        return $model->result(TransferQueryResult::class, 'wechat');
    }

    private function api(): WechatApi
    {
        $channel = new PaymentChannel(WechatApi::CHANNEL_H5, 'wechat', WechatApi::CHANNEL_H5, PaymentFlow::REDIRECT);

        return new WechatApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'wechat',
                    'wechat',
                    [],
                    [WechatApi::CHANNEL_H5 => $channel],
                    new PaymentGatewayFactory('wechat', WechatApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: [
                    WechatApi::MCH_ID => '1900000001',
                    WechatApi::MCH_SERIAL => 'MCH-SERIAL',
                    WechatApi::MCH_PRIVATE_KEY => $this->merchantPrivateKey(),
                    WechatApi::PLATFORM_CERT => $this->platformPublicKey(),
                    WechatApi::PLATFORM_SERIAL => self::PLATFORM_SERIAL,
                ],
                params: [WechatApi::PARAM_APP_ID => 'wx-app-id'],
                runtimeParams: [],
                settings: [],
                mode: 'live',
            ),
            '',
            '',
            $this->handler->stack(),
        );
    }
}
