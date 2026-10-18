<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

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
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferAction;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayTransferAction::class)]
class AlipayTransferActionTest extends TestCase
{
    private const SECRET = 'unit-test-secret';

    private PaymentCredentialCipher $cipher;

    protected function setUp(): void
    {
        $this->cipher = new PaymentCredentialCipher(self::SECRET);
    }

    public function testSupportsOnlyTransferRequestsWithAPaymentGatewayModel(): void
    {
        $action = new AlipayTransferAction($this->cipher);

        $transfer = $this->transfer();
        $recipient = $this->recipient('alipay_user_id');

        static::assertTrue($action->supports(new PaymentTransferRequest(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $recipient,
        )));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        // the model is what the action records on - a request without one must not be selected
        $broken = new PaymentTransferRequest(PaymentGatewayModel::forTransfer($transfer), $transfer, $recipient);
        $broken->setModel(new \stdClass());
        static::assertFalse($action->supports($broken));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayTransferAction($this->cipher);

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayTransferAction($this->cipher);

        $this->expectExceptionObject(PaymentException::actionWithoutApi(AlipayTransferAction::class));

        $action->execute($this->request());
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new AlipayTransferAction($this->cipher);

        $this->expectExceptionObject(PaymentException::unsupportedApi(AlipayTransferAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheTransferRequest(): void
    {
        $action = new AlipayTransferAction($this->cipher);
        // without the app id the sdk fails while it is built, before it would talk to the channel
        $action->setApi($this->api());

        $request = $this->request();

        try {
            $action->execute($request);
            static::fail('The transfer was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $content = $model->request()['content'];

        static::assertSame('OUT-1', $content['out_biz_no']);
        static::assertSame('12.34', $content['trans_amount']);
        static::assertSame('DIRECT_TRANSFER', $content['biz_scene']);
        static::assertSame('佣金报酬', $content['transfer_scene_name']);
        // the result of the interface arrives through the ant message on the application gateway,
        // never through a notify url - there is no such parameter on this interface
        static::assertArrayNotHasKey('notify_url', $content);
        static::assertArrayNotHasKey('notify_url', $model->request());
    }

    public function testExecuteThrowsWhenTheSceneNameOfTheChannelIsMissing(): void
    {
        $action = new AlipayTransferAction($this->cipher);
        $action->setApi($this->api());

        $request = $this->request(channelParams: []);

        $this->expectExceptionObject(PaymentException::missingParameter('channel_params.transfer_scene_name'));

        $action->execute($request);
    }

    public function testTheContentOfATransferToAUserIdCarriesTheIdentityAndTheOptionalName(): void
    {
        $recipient = $this->recipient('alipay_user_id', holderName: '张三');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $content = AlipayTransferAction::contentOf($this->transfer(), $recipient, $this->channelParams(), $this->cipher, '佣金结算');

        static::assertSame([
            'out_biz_no' => 'OUT-1',
            'trans_amount' => '12.34',
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene' => 'DIRECT_TRANSFER',
            'order_title' => '佣金结算',
            'transfer_scene_name' => '佣金报酬',
            'payee_info' => [
                'identity_type' => 'ALIPAY_USER_ID',
                'identity' => '2088000000000000',
                'name' => '张三',
            ],
        ], $content);
    }

    public function testTheContentOfATransferToAUserIdOmitsANameItDoesNotKnow(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $content = AlipayTransferAction::contentOf($this->transfer(), $recipient, $this->channelParams(), $this->cipher, '佣金结算');

        static::assertArrayNotHasKey('name', $content['payee_info']);
    }

    public function testTheContentOfATransferToALogonIdDemandsTheName(): void
    {
        $recipient = $this->recipient('alipay_logon_id');
        $recipient->identityValue = $this->cipher->encryptValue('13800138000');

        $this->expectExceptionObject(PaymentException::missingParameter('payee_info.name'));

        AlipayTransferAction::contentOf($this->transfer(), $recipient, $this->channelParams(), $this->cipher, '佣金结算');
    }

    public function testTheContentOfATransferToALogonIdCarriesTheIdentityAndTheName(): void
    {
        $recipient = $this->recipient('alipay_logon_id', holderName: '张三');
        $recipient->identityValue = $this->cipher->encryptValue('13800138000');

        $content = AlipayTransferAction::contentOf($this->transfer(), $recipient, $this->channelParams(), $this->cipher, '佣金结算');

        static::assertSame('ALIPAY_LOGON_ID', $content['payee_info']['identity_type']);
        static::assertSame('13800138000', $content['payee_info']['identity']);
        static::assertSame('张三', $content['payee_info']['name']);
    }

    public function testTheContentOfATransferToAnOpenIdCarriesTheIdentityAlone(): void
    {
        $recipient = $this->recipient('alipay_open_id', holderName: '张三');
        $recipient->identityValue = $this->cipher->encryptValue('openid-1');

        $content = AlipayTransferAction::contentOf($this->transfer(), $recipient, $this->channelParams(), $this->cipher, '佣金结算');

        static::assertSame('ALIPAY_OPEN_ID', $content['payee_info']['identity_type']);
        static::assertSame('openid-1', $content['payee_info']['identity']);
        static::assertArrayNotHasKey('name', $content['payee_info']);
    }

    public function testTheContentOfATransferToACardCarriesTheBankcardExtInfo(): void
    {
        $recipient = $this->recipient('bankcard', holderName: '张三');
        $recipient->identityValue = $this->cipher->encryptValue('6222020000000000');
        $recipient->bankInfo = [
            'account_type' => '2',
            'inst_name' => '招商银行',
            'inst_province' => '广东省',
            'inst_city' => '深圳市',
            'inst_branch_name' => '深圳分行营业部',
            'bank_code' => '',
            'memo' => 'a key the interface does not know',
        ];

        $content = AlipayTransferAction::contentOf($this->transfer(), $recipient, $this->channelParams(), $this->cipher, '佣金结算');

        static::assertSame('TRANS_BANKCARD_NO_PWD', $content['product_code']);
        static::assertSame('BANKCARD_ACCOUNT', $content['payee_info']['identity_type']);
        static::assertSame('6222020000000000', $content['payee_info']['identity']);
        static::assertSame('张三', $content['payee_info']['name']);
        // only the keys the interface knows and the recipient carries are sent
        static::assertSame([
            'account_type' => '2',
            'inst_name' => '招商银行',
            'inst_province' => '广东省',
            'inst_city' => '深圳市',
            'inst_branch_name' => '深圳分行营业部',
        ], $content['payee_info']['bankcard_ext_info']);
    }

    public function testTheContentOfATransferToACardDemandsTheName(): void
    {
        $recipient = $this->recipient('bankcard');
        $recipient->identityValue = $this->cipher->encryptValue('6222020000000000');
        $recipient->bankInfo = ['account_type' => '2'];

        $this->expectExceptionObject(PaymentException::missingParameter('payee_info.name'));

        AlipayTransferAction::contentOf($this->transfer(), $recipient, $this->channelParams(), $this->cipher, '佣金结算');
    }

    public function testTheContentOfATransferRefusesAnIdentityTypeOfAnotherInterface(): void
    {
        $recipient = $this->recipient('wechat_open_id');
        $recipient->identityValue = $this->cipher->encryptValue('openid-1');

        $this->expectExceptionObject(PaymentException::transferIdentityNotSupported('alipay', 'wechat_open_id'));

        AlipayTransferAction::contentOf($this->transfer(), $recipient, $this->channelParams(), $this->cipher, '佣金结算');
    }

    public function testTheContentCarriesTheReportInfosOfTheChannelUnchanged(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $reportInfos = [
            ['info_type' => 'COMMISSION', 'info_content' => '月度佣金'],
        ];

        $content = AlipayTransferAction::contentOf(
            $this->transfer(),
            $recipient,
            $this->channelParams() + ['transfer_scene_report_infos' => $reportInfos],
            $this->cipher,
            '佣金结算',
        );

        static::assertSame($reportInfos, $content['transfer_scene_report_infos']);
    }

    public function testTheRemarkOfTheChannelRidesAlong(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $content = AlipayTransferAction::contentOf(
            $this->transfer(),
            $recipient,
            $this->channelParams() + ['remark' => '10月佣金'],
            $this->cipher,
            '佣金结算',
        );

        static::assertSame('10月佣金', $content['remark']);
    }

    public function testAPayoutBelowFiftyThousandYuanNeedsNoRemark(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $transfer = $this->transfer();
        // 49999.99 yuan - one fen below the amount alipay demands a remark for
        $transfer->amount = 4_999_999;

        $content = AlipayTransferAction::contentOf($transfer, $recipient, $this->channelParams(), $this->cipher, '佣金结算');

        static::assertArrayNotHasKey('remark', $content);
    }

    public function testAFiftyThousandYuanPayoutWithoutARemarkIsRefused(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $transfer = $this->transfer();
        $transfer->amount = 5_000_000;

        // a rejection of the interface would burn the out business number - the call is refused here
        $this->expectExceptionObject(PaymentException::missingParameter('channel_params.remark'));

        AlipayTransferAction::contentOf($transfer, $recipient, $this->channelParams(), $this->cipher, '佣金结算');
    }

    public function testAFiftyThousandYuanPayoutWithARemarkIsBuilt(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $transfer = $this->transfer();
        $transfer->amount = 5_000_000;

        $content = AlipayTransferAction::contentOf(
            $transfer,
            $recipient,
            $this->channelParams() + ['remark' => '大额佣金'],
            $this->cipher,
            '佣金结算',
        );

        static::assertSame('大额佣金', $content['remark']);
    }

    public function testTheOrderTitleIsTheSubjectOfTheModel(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $transfer = $this->transfer();
        $model = PaymentGatewayModel::forTransfer($transfer);

        $content = AlipayTransferAction::contentOf(
            $transfer,
            $recipient,
            $this->channelParams(),
            $this->cipher,
            (string) $model->offsetGet(PaymentGatewayModel::SUBJECT),
        );

        // the subject the model carries is the one that goes on the wire - one source, no divergence
        static::assertSame('佣金结算', $content['order_title']);
    }

    public function testAnEmptySubjectOfTheModelFallsBackToTheDefaultValue(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $transfer = $this->transfer();
        $transfer->metadata = null;

        // a model that did not pass forTransfer() carries no subject - the anomaly the action must
        // not forward, because an empty order title is a rejection at the interface
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::AMOUNT => $transfer->amount,
            PaymentGatewayModel::CURRENCY => $transfer->currency,
        ]);

        $content = AlipayTransferAction::contentOf(
            $transfer,
            $recipient,
            $this->channelParams(),
            $this->cipher,
            $model->offsetExists(PaymentGatewayModel::SUBJECT) ? (string) $model->offsetGet(PaymentGatewayModel::SUBJECT) : '',
        );

        static::assertSame(PaymentGatewayModel::DEFAULT_SUBJECT, $content['order_title']);
    }

    public function testTheOrderTitleIsCutAtTheLengthAlipayAccepts(): void
    {
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        $content = AlipayTransferAction::contentOf(
            $this->transfer(),
            $recipient,
            $this->channelParams(),
            $this->cipher,
            str_repeat('佣', 41),
        );

        static::assertSame(str_repeat('佣', 40), $content['order_title']);
    }

    /**
     * Alipay wants yuan with two decimals, the platform stores fen - the conversion is integer only,
     * a float in between would lose cents.
     */
    public static function amounts(): iterable
    {
        yield 'one fen' => [1, '0.01'];
        yield 'one yuan' => [100, '1.00'];
        yield 'twelve yuan' => [1234, '12.34'];
        yield '1234.56 yuan' => [123456, '1234.56'];
        yield 'one million yuan' => [100000000, '1000000.00'];
    }

    #[DataProvider('amounts')]
    public function testTheAmountIsConvertedFromFenToYuanWithoutFloats(int $minor, string $expected): void
    {
        static::assertSame($expected, AlipayTransferAction::amount($minor));
    }

    public static function answers(): iterable
    {
        yield 'success carries the references' => [
            ['code' => '10000', 'status' => 'SUCCESS', 'order_id' => 'O-1', 'pay_fund_order_id' => 'PF-1'],
            TransferResult::STATE_SUCCEEDED,
            'O-1',
            'PF-1',
            null,
        ];
        yield 'fail names its code' => [
            ['code' => '10000', 'status' => 'FAIL', 'sub_code' => 'PAYEE_USERINFO_STATUS_ERROR'],
            TransferResult::STATE_FAILED,
            null,
            null,
            'PAYEE_USERINFO_STATUS_ERROR',
        ];
        yield 'fail without a code names the status' => [
            ['code' => '10000', 'status' => 'FAIL'],
            TransferResult::STATE_FAILED,
            null,
            null,
            'FAIL',
        ];
        yield 'dealing is the normal course' => [
            ['code' => '10000', 'status' => 'DEALING', 'order_id' => 'O-1'],
            TransferResult::STATE_PROCESSING,
            'O-1',
            null,
            null,
        ];
        yield 'refund is the bounced card' => [
            ['code' => '10000', 'status' => 'REFUND', 'order_id' => 'O-1'],
            TransferResult::STATE_RETURNED,
            'O-1',
            null,
            null,
        ];
        yield 'a missing status is unknown' => [
            ['code' => '10000'],
            TransferResult::STATE_UNKNOWN,
            null,
            null,
            null,
        ];
        yield 'a system error waits for the query' => [
            ['code' => '4444', 'sub_code' => 'SYSTEM_ERROR', 'sub_msg' => '系统繁忙'],
            TransferResult::STATE_UNKNOWN,
            null,
            null,
            null,
        ];
        yield 'a payment fail waits for the query as well' => [
            ['code' => '4444', 'sub_code' => 'PAYMENT_FAIL'],
            TransferResult::STATE_UNKNOWN,
            null,
            null,
            null,
        ];
        yield 'a rejected payee is a failure in its own words' => [
            ['code' => '4444', 'sub_code' => 'PAYEE_NOT_EXIST', 'sub_msg' => '收款账号不存在'],
            TransferResult::STATE_FAILED,
            null,
            null,
            'PAYEE_NOT_EXIST',
        ];
    }

    #[DataProvider('answers')]
    public function testTheAnswerIsNarrowedToWhatTheTransferMeans(
        array $answer,
        string $expectedState,
        ?string $expectedRef,
        ?string $expectedStageRef,
        ?string $expectedFailureCode,
    ): void {
        $result = AlipayTransferAction::resultOf($answer);

        static::assertSame($expectedState, $result->state);
        static::assertSame($expectedRef, $result->providerRef);
        static::assertSame($expectedStageRef, $result->providerStageRef);
        static::assertSame($expectedFailureCode, $result->failureCode);
    }

    private function transfer(): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->outNo = 'OUT-1';
        $transfer->provider = 'alipay';
        $transfer->amount = 1234;
        $transfer->currency = 'CNY';
        $transfer->metadata = ['subject' => '佣金结算'];

        return $transfer;
    }

    private function recipient(string $identityType, ?string $holderName = null): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->provider = 'alipay';
        $recipient->identityType = $identityType;
        $recipient->holderName = $holderName;

        return $recipient;
    }

    /**
     * @return array<string, mixed>
     */
    private function channelParams(): array
    {
        return ['transfer_scene_name' => '佣金报酬'];
    }

    private function request(array $channelParams = ['transfer_scene_name' => '佣金报酬']): PaymentTransferRequest
    {
        $transfer = $this->transfer();
        $recipient = $this->recipient('alipay_user_id');
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        return new PaymentTransferRequest(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $recipient,
            $channelParams,
        );
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): AlipayApi
    {
        $channel = new PaymentChannel(AlipayApi::CHANNEL_H5, 'alipay', AlipayApi::CHANNEL_H5, PaymentFlow::REDIRECT);

        return new AlipayApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'alipay',
                    'alipay',
                    [],
                    [AlipayApi::CHANNEL_H5 => $channel],
                    new PaymentGatewayFactory('alipay', AlipayApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: [],
                mode: 'own',
            ),
            'https://example.com/notify',
            '',
        );
    }
}
