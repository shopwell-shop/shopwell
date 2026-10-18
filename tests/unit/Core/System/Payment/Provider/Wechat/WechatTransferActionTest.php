<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferAction;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatTransferAction::class)]
class WechatTransferActionTest extends TestCase
{
    private const SECRET = 'unit-test-cipher-secret';

    private const PLATFORM_SERIAL = 'PLATFORM-SERIAL';

    private const TRANSFER_PATH = '/v3/fund-app/mch-transfer/transfer-bills';

    /**
     * @var array{merchant: array{0: string, 1: string}, platform: array{0: string, 1: string}}|null
     */
    private static ?array $keys = null;

    private FakeWechatHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new FakeWechatHandler($this->platformPrivateKey(), self::PLATFORM_SERIAL);
    }

    public function testSupportsOnlyTransferRequestsWithAPaymentGatewayModel(): void
    {
        $action = new WechatTransferAction($this->cipher());

        static::assertTrue($action->supports($this->request()));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
    }

    /**
     * The payum contract is easy to break silently: a request still of the right class but whose model
     * was replaced is no transfer the action may execute.
     */
    public function testSupportsRejectsARequestWhoseModelIsNotAPaymentGatewayModel(): void
    {
        $request = $this->request();
        $request->setModel(new \stdClass());

        static::assertFalse((new WechatTransferAction($this->cipher()))->supports($request));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new WechatTransferAction($this->cipher());

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatTransferAction($this->cipher());

        $this->expectExceptionObject(PaymentException::actionWithoutApi(WechatTransferAction::class));

        $action->execute($this->request());
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new WechatTransferAction($this->cipher());

        $this->expectExceptionObject(PaymentException::unsupportedApi(WechatTransferAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    /**
     * The body of the call: the appid of the channel, the out number as the idempotency key, the
     * *decrypted* openid, the amount in fen without any conversion, and the evidence of the scene
     * exactly as the caller filed it.
     */
    public function testExecuteBuildsTheTransferBody(): void
    {
        $this->handler->answer(200, ['state' => 'ACCEPTED', 'transfer_bill_no' => 'BILL-1']);

        $reportInfos = [
            ['info_type' => '岗位类型', 'info_content' => '配送员'],
            ['info_type' => '报酬说明', 'info_content' => '九月佣金'],
        ];

        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request(
            amount: 123_456,
            subject: '佣金',
            channelParams: [
                'transfer_scene_id' => '1005',
                'transfer_scene_report_infos' => $reportInfos,
            ],
        );

        $action->execute($request);

        $call = $this->handler->only();

        static::assertSame('POST', $call['method']);
        static::assertSame(self::TRANSFER_PATH, $call['path']);

        $body = $call['body'];

        static::assertSame('wx-app-id', $body['appid']);
        static::assertSame('OUT-1', $body['out_bill_no']);
        static::assertSame('1005', $body['transfer_scene_id']);
        // the ciphertext never leaves the platform - the plain openid is what goes out
        static::assertSame('openid-of-the-shopper', $body['openid']);
        static::assertSame('佣金', $body['transfer_remark']);
        static::assertSame($reportInfos, $body['transfer_scene_report_infos']);

        // the platform stores fen and wechat reads fen: a division here would move a hundredth
        static::assertSame(123_456, $body['transfer_amount']);
    }

    /**
     * The remark is cut because wechat has a limit, and it is cut on bytes: a chinese remark of 32
     * characters passes a character count and still overflows every limit the field has.
     */
    public function testTheRemarkIsCutOnBytes(): void
    {
        $this->handler->answer(200, ['state' => 'ACCEPTED', 'transfer_bill_no' => 'BILL-1']);

        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        $subject = str_repeat('佣', 40);
        $action->execute($this->request(subject: $subject));

        $remark = (string) $this->handler->only()['body']['transfer_remark'];

        static::assertSame(mb_strcut($subject, 0, 32), $remark);
        static::assertLessThanOrEqual(32, \strlen($remark));
        static::assertTrue(mb_check_encoding($remark, 'UTF-8'));
    }

    public function testAMissingSceneIsRefusedBeforeTheInterfaceIsCalled(): void
    {
        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        try {
            $action->execute($this->request(channelParams: ['transfer_scene_report_infos' => [['info_type' => 'a', 'info_content' => 'b']]]));
            static::fail('The transfer was expected to be refused without a scene.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::MISSING_PARAMETER, $exception->getErrorCode());
            static::assertStringContainsString('channel_params.transfer_scene_id', $exception->getMessage());
        }

        // wechat refuses every call without the scene, so the out number must not be burned on it
        static::assertSame([], $this->handler->requests);
    }

    public function testAnIdentityOfAnotherInterfaceIsRefusedBeforeAnyRequestIsSent(): void
    {
        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        try {
            $action->execute($this->request(identityType: 'alipay_user_id'));
            static::fail('The transfer was expected to be refused for an identity of another interface.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_IDENTITY_NOT_SUPPORTED, $exception->getErrorCode());
        }

        static::assertSame([], $this->handler->requests);
    }

    /**
     * The single most important field of this action: the `package_info` is what the shopper accepts
     * the transfer with, it is issued once and can not be recovered.
     */
    public function testThePackageInfoSurvivesIntactIntoTheResult(): void
    {
        $package = str_repeat('package-info-with-a-long-body-', 10);

        $this->handler->answer(200, [
            'state' => 'WAIT_USER_CONFIRM',
            'transfer_bill_no' => 'BILL-1',
            'package_info' => $package,
        ]);

        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_AWAITING_CONFIRM, $result->state);
        static::assertSame('BILL-1', $result->providerRef);
        static::assertSame($package, $result->confirmPayload);
    }

    /**
     * The assertion that protects the books: a cancelled bill is money that never arrived, not money
     * that came back. Booking `returned` would claim the platform got its money again.
     */
    public function testACancelledBillIsAFailureAndNotAReturn(): void
    {
        $this->handler->answer(200, [
            'state' => 'CANCELLED',
            'transfer_bill_no' => 'BILL-1',
        ]);

        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_FAILED, $result->state);
        static::assertSame('CANCELLED', $result->failureCode);
        static::assertNull($result->confirmPayload);
    }

    public function testTheNameIsEncryptedWithThePlatformCertificateAtTheThreshold(): void
    {
        $this->handler->answer(200, ['state' => 'ACCEPTED', 'transfer_bill_no' => 'BILL-1']);

        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        $action->execute($this->request(amount: 200_000, holderName: '张三'));

        $call = $this->handler->only();

        static::assertArrayHasKey('user_name', $call['body']);
        static::assertSame(self::PLATFORM_SERIAL, $call['headers']['Wechatpay-Serial']);

        $encrypted = base64_decode((string) $call['body']['user_name'], true);
        static::assertIsString($encrypted);

        // wechat decrypts the field with the certificate the header names
        static::assertTrue(openssl_private_decrypt($encrypted, $name, $this->platformPrivateKey(), \OPENSSL_PKCS1_OAEP_PADDING));
        static::assertSame('张三', $name);
    }

    /**
     * Below the threshold neither the field nor the header is sent - a header without an encrypted
     * field and an encrypted field without a header are both refused by wechat.
     */
    public function testTheNameAndTheSerialHeaderAreBothAbsentBelowTheThreshold(): void
    {
        $this->handler->answer(200, ['state' => 'ACCEPTED', 'transfer_bill_no' => 'BILL-1']);

        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        $action->execute($this->request(amount: 199_999, holderName: '张三'));

        $call = $this->handler->only();

        static::assertArrayNotHasKey('user_name', $call['body']);
        static::assertArrayNotHasKey('Wechatpay-Serial', $call['headers']);
    }

    public function testARejectionOfTheChannelIsWrappedAndKeepsTheEvidence(): void
    {
        $this->handler->answer(500, ['code' => 'SYSTEM_ERROR', 'message' => 'the channel is busy']);

        $action = new WechatTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request();

        try {
            $action->execute($request);
            static::fail('The submission was expected to fail on a channel error.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        static::assertSame('OUT-1', $model->request()['content']['out_bill_no']);
        static::assertArrayHasKey('error', $model->response());
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function billStates(): iterable
    {
        yield 'accepted is on its way' => ['ACCEPTED', TransferResult::STATE_PROCESSING, null];
        yield 'processing is on its way' => ['PROCESSING', TransferResult::STATE_PROCESSING, null];
        yield 'transfering is on its way' => ['TRANSFERING', TransferResult::STATE_PROCESSING, null];
        yield 'canceling is on its way' => ['CANCELING', TransferResult::STATE_PROCESSING, null];
        yield 'success arrived' => ['SUCCESS', TransferResult::STATE_SUCCEEDED, null];
        yield 'wait for confirm waits for the shopper' => ['WAIT_USER_CONFIRM', TransferResult::STATE_AWAITING_CONFIRM, null];
        yield 'fail names itself' => ['FAIL', TransferResult::STATE_FAILED, 'FAIL'];
        // never arrived, nothing came back
        yield 'cancelled is a failure' => ['CANCELLED', TransferResult::STATE_FAILED, 'CANCELLED'];
        yield 'a new state stays unknown' => ['SOMETHING_NEW', TransferResult::STATE_UNKNOWN, null];
    }

    #[DataProvider('billStates')]
    public function testTheBillStateIsNarrowedToWhatTheTransferMeans(
        string $state,
        string $expectedState,
        ?string $expectedFailureCode,
    ): void {
        $result = WechatTransferAction::resultOf([
            'state' => $state,
            'transfer_bill_no' => 'BILL-1',
        ]);

        static::assertSame($expectedState, $result->state);
        static::assertSame('BILL-1', $result->providerRef);
        static::assertSame($expectedFailureCode, $result->failureCode);
    }

    public function testAFailureKeepsTheReasonOfTheInterface(): void
    {
        $result = WechatTransferAction::resultOf([
            'state' => 'FAIL',
            'transfer_bill_no' => 'BILL-1',
            'fail_reason' => 'USER_NAME_NOT_MATCH',
        ]);

        static::assertSame(TransferResult::STATE_FAILED, $result->state);
        static::assertSame('USER_NAME_NOT_MATCH', $result->failureCode);
    }

    private function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher(self::SECRET);
    }

    private function merchantPrivateKey(): string
    {
        return self::keys()['merchant'][0];
    }

    private function platformPrivateKey(): string
    {
        return self::keys()['platform'][0];
    }

    private function platformPublicKey(): string
    {
        return self::keys()['platform'][1];
    }

    /**
     * Key generation is the slow part of these tests and the keys carry no state - one pair per run
     * is enough.
     *
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

    /**
     * @param array<string, mixed> $channelParams
     */
    private function request(
        int $amount = 1000,
        string $subject = '转账',
        string $identityType = 'wechat_open_id',
        string $holderName = '',
        array $channelParams = ['transfer_scene_id' => '1005'],
    ): PaymentTransferRequest {
        $transfer = new PaymentTransferEntity();
        $transfer->outNo = 'OUT-1';
        $transfer->provider = 'wechat';
        $transfer->channel = 'wechat_h5';
        $transfer->amount = $amount;
        $transfer->currency = 'CNY';
        $transfer->metadata = ['subject' => $subject];

        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'rec-1';
        $recipient->provider = 'wechat';
        $recipient->identityType = $identityType;
        $recipient->identityValue = $this->cipher()->encryptValue('openid-of-the-shopper');
        $recipient->holderName = $holderName === '' ? null : $holderName;
        $recipient->onboardingStatus = 'registered';
        $recipient->payoutEnabled = true;

        return new PaymentTransferRequest(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $recipient,
            $channelParams,
        );
    }

    private function resultOf(PaymentTransferRequest $request): TransferResult
    {
        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        return $model->result(TransferResult::class, 'wechat');
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
