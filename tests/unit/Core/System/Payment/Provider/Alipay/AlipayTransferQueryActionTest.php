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
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferQueryAction;
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
#[CoversClass(AlipayTransferQueryAction::class)]
class AlipayTransferQueryActionTest extends TestCase
{
    private PaymentCredentialCipher $cipher;

    protected function setUp(): void
    {
        $this->cipher = new PaymentCredentialCipher('unit-test-secret');
    }

    public function testSupportsOnlyTransferQueriesWithAPaymentGatewayModel(): void
    {
        $action = new AlipayTransferQueryAction();

        $transfer = new PaymentTransferEntity();
        $transfer->outNo = 'OUT-1';
        $transfer->provider = 'alipay';
        $transfer->amount = 1234;
        $transfer->currency = 'CNY';

        $recipient = new PaymentTransferRecipientEntity();
        $recipient->provider = 'alipay';
        $recipient->identityType = 'alipay_user_id';

        static::assertTrue($action->supports(new PaymentTransferQuery(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $recipient,
        )));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        // the model is what the action records on - a request without one must not be selected
        $broken = new PaymentTransferQuery(PaymentGatewayModel::forTransfer($transfer), $transfer, $recipient);
        $broken->setModel(new \stdClass());
        static::assertFalse($action->supports($broken));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayTransferQueryAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayTransferQueryAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(AlipayTransferQueryAction::class));

        $action->execute($this->query());
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheQueryRequest(): void
    {
        $action = new AlipayTransferQueryAction();
        // without the app id the sdk fails while it is built, before it would talk to the channel
        $action->setApi($this->api());

        $query = $this->query();

        try {
            $action->execute($query);
            static::fail('The query was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $model = $query->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        // queried by the out business number, alipay demands the product code and the scene as well
        $request = $model->request();

        static::assertSame(AlipayApi::METHOD_COMMON_QUERY, $request['method']);
        static::assertSame('OUT-1', $request['out_biz_no']);
        static::assertSame('TRANS_ACCOUNT_NO_PWD', $request['product_code']);
        static::assertSame('DIRECT_TRANSFER', $request['biz_scene']);
    }

    public function testExecuteRefusesAnIdentityTypeOfAnotherInterface(): void
    {
        $action = new AlipayTransferQueryAction();
        $action->setApi($this->api());

        $this->expectExceptionObject(PaymentException::transferIdentityNotSupported('alipay', 'wechat_open_id'));

        $action->execute($this->query('wechat_open_id'));
    }

    public static function answers(): iterable
    {
        yield 'success' => [
            ['code' => '10000', 'status' => 'SUCCESS', 'order_id' => 'O-1'],
            TransferResult::STATE_SUCCEEDED,
            'SUCCESS',
            'O-1',
            null,
        ];
        yield 'fail carries the error code of the interface' => [
            ['code' => '10000', 'status' => 'FAIL', 'error_code' => 'PAYEE_NOT_EXIST'],
            TransferResult::STATE_FAILED,
            'FAIL',
            null,
            'PAYEE_NOT_EXIST',
        ];
        yield 'dealing' => [
            ['code' => '10000', 'status' => 'DEALING'],
            TransferResult::STATE_PROCESSING,
            'DEALING',
            null,
            null,
        ];
        yield 'refund' => [
            ['code' => '10000', 'status' => 'REFUND'],
            TransferResult::STATE_RETURNED,
            'REFUND',
            null,
            null,
        ];
        yield 'a status the interface does not document is unknown' => [
            ['code' => '10000', 'status' => 'SOMETHING_ELSE'],
            TransferResult::STATE_UNKNOWN,
            'SOMETHING_ELSE',
            null,
            null,
        ];
        yield 'a rejected query is no answer about the transfer' => [
            ['code' => '4444', 'sub_code' => 'SYSTEM_ERROR'],
            TransferResult::STATE_UNKNOWN,
            'SYSTEM_ERROR',
            null,
            null,
        ];
    }

    #[DataProvider('answers')]
    public function testTheAnswerIsNarrowedToWhatTheTransferMeans(
        array $answer,
        string $expectedState,
        string $expectedStatus,
        ?string $expectedRef,
        ?string $expectedFailureCode,
    ): void {
        $result = AlipayTransferQueryAction::resultOf($answer);

        static::assertSame($expectedState, $result->state);
        static::assertSame($expectedStatus, $result->providerStatus);
        static::assertSame($expectedRef, $result->providerRef);
        static::assertSame($expectedFailureCode, $result->failureCode);
    }

    private function query(string $identityType = 'alipay_user_id'): PaymentTransferQuery
    {
        $transfer = new PaymentTransferEntity();
        $transfer->outNo = 'OUT-1';
        $transfer->provider = 'alipay';
        $transfer->amount = 1234;
        $transfer->currency = 'CNY';

        $recipient = new PaymentTransferRecipientEntity();
        $recipient->provider = 'alipay';
        $recipient->identityType = $identityType;
        $recipient->identityValue = $this->cipher->encryptValue('2088000000000000');

        return new PaymentTransferQuery(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $recipient,
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
