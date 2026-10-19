<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
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
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferAction;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Stripe\ApiRequestor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeTransferAction::class)]
class StripeTransferActionTest extends TestCase
{
    private const TRANSFER_PATH = '/v1/transfers';

    private const PAYOUT_PATH = '/v1/payouts';

    private FakeStripeHttpClient $http;

    protected function setUp(): void
    {
        // the sdk of stripe talks through a global http client - the fake below intercepts every
        // request before it would leave the process, and records it for the assertions
        $this->http = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->http);
    }

    public function testSupportsOnlyTransferRequestsWithAPaymentGatewayModel(): void
    {
        $action = new StripeTransferAction();

        static::assertTrue($action->supports($this->request()));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new StripeTransferAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new StripeTransferAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(StripeTransferAction::class));

        $action->execute($this->request());
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new StripeTransferAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(StripeTransferAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    /**
     * Stripe refuses every payout of an account whose onboarding is not finished - refusing it here
     * means not a single request is built, and the idempotency keys of the transfer are not burned
     * on a rejection the interface was guaranteed to give.
     */
    public function testExecuteRefusesARecipientThatIsNotReadyBeforeAnyRequestIsSent(): void
    {
        $action = new StripeTransferAction();
        $action->setApi($this->api());

        $request = $this->request(payoutEnabled: false);

        try {
            $action->execute($request);
            static::fail('The transfer was expected to be refused for a recipient that is not ready.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_RECIPIENT_NOT_READY, $exception->getErrorCode());
            static::assertSame(409, $exception->getStatusCode());
        }

        static::assertSame(0, $this->http->count(self::TRANSFER_PATH));
        static::assertSame(0, $this->http->count(self::PAYOUT_PATH));
    }

    public function testExecuteRefusesAnIdentityOfAnotherInterface(): void
    {
        $action = new StripeTransferAction();
        $action->setApi($this->api());

        $this->expectExceptionObject(PaymentException::transferIdentityNotSupported('stripe', 'paypal_email'));

        $action->execute($this->request(identityType: 'paypal_email'));
    }

    public function testExecuteRefusesARecipientWithoutAConnectedAccountId(): void
    {
        $action = new StripeTransferAction();
        $action->setApi($this->api());

        $this->expectExceptionObject(PaymentException::transferRecipientIncomplete('provider_account_id'));

        $action->execute($this->request(accountId: 'user_1'));
    }

    /**
     * The two stages in one call: the transfer moves the money into the connected account, the
     * payout moves it out of it - and nothing between the two is converted or guessed.
     */
    public function testExecuteCreatesTheTransferAndThenThePayout(): void
    {
        $this->http->answers[self::TRANSFER_PATH] = [200, self::json(['id' => 'tr_1', 'object' => 'transfer', 'reversed' => false])];
        $this->http->answers[self::PAYOUT_PATH] = [200, self::json(['id' => 'po_1', 'object' => 'payout', 'status' => 'paid'])];

        $action = new StripeTransferAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        static::assertSame(1, $this->http->count(self::TRANSFER_PATH));
        static::assertSame(1, $this->http->count(self::PAYOUT_PATH));

        $transfer = $this->http->requestOf(self::TRANSFER_PATH);

        static::assertSame('post', $transfer['method']);
        // the platform stores the smallest unit of the currency and stripe takes exactly that - no
        // conversion happens, zero decimal currencies included
        static::assertSame(1000, $transfer['params']['amount']);
        static::assertSame('usd', $transfer['params']['currency']);
        static::assertSame('acct_1', $transfer['params']['destination']);
        static::assertSame(['out_no' => 'OUT-1'], $transfer['params']['metadata']);
        static::assertSame('OUT-1:transfer', $this->http->headerOf(self::TRANSFER_PATH, 'Idempotency-Key'));

        $payout = $this->http->requestOf(self::PAYOUT_PATH);

        static::assertSame('post', $payout['method']);
        static::assertSame(1000, $payout['params']['amount']);
        static::assertSame('usd', $payout['params']['currency']);
        static::assertSame(['out_no' => 'OUT-1'], $payout['params']['metadata']);
        // the payout rides the account header instead - with a `destination` stripe would pay out of
        // the platform balance and not even complain about it
        static::assertArrayNotHasKey('destination', $payout['params']);
        static::assertSame('acct_1', $this->http->headerOf(self::PAYOUT_PATH, 'Stripe-Account'));
        static::assertSame('OUT-1:payout', $this->http->headerOf(self::PAYOUT_PATH, 'Idempotency-Key'));

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $result = $model->result(TransferResult::class, 'stripe');
        static::assertSame(TransferResult::STATE_SUCCEEDED, $result->state);
        static::assertSame('po_1', $result->providerRef);
        static::assertSame('tr_1', $result->providerStageRef);
        static::assertNull($result->failureCode);
    }

    /**
     * The hard rule of the two stages: a transfer that succeeded is never created again - the retry
     * resumes at the payout. Creating the transfer twice would move a second amount out of the
     * platform balance, and the interface would book it without a word.
     */
    public function testAResumedPayoutDoesNotCreateTheTransferAgain(): void
    {
        $this->http->answers[self::PAYOUT_PATH] = [200, self::json(['id' => 'po_1', 'object' => 'payout', 'status' => 'in_transit'])];

        $action = new StripeTransferAction();
        $action->setApi($this->api());

        $request = $this->request(stageRef: 'tr_1');
        $action->execute($request);

        static::assertSame(0, $this->http->count(self::TRANSFER_PATH));
        static::assertSame(1, $this->http->count(self::PAYOUT_PATH));
        static::assertSame('OUT-1:payout', $this->http->headerOf(self::PAYOUT_PATH, 'Idempotency-Key'));

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $result = $model->result(TransferResult::class, 'stripe');
        static::assertSame(TransferResult::STATE_PROCESSING, $result->state);
        static::assertSame('po_1', $result->providerRef);
        static::assertSame('tr_1', $result->providerStageRef);
    }

    /**
     * The transfer has moved the money into the connected account - when the payout fails the
     * stage reference is the only evidence a support case has that the money sits there, and the
     * resume of the next attempt is made with it.
     */
    public function testTheTransferStageIsKeptWhenThePayoutFails(): void
    {
        $this->http->answers[self::TRANSFER_PATH] = [200, self::json(['id' => 'tr_1', 'object' => 'transfer'])];
        $this->http->answers[self::PAYOUT_PATH] = [400, self::json([
            'error' => ['code' => 'insufficient_funds', 'message' => 'The balance is too small.'],
        ])];

        $action = new StripeTransferAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $result = $model->result(TransferResult::class, 'stripe');
        static::assertSame(TransferResult::STATE_FAILED, $result->state);
        static::assertSame('insufficient_funds', $result->failureCode);
        // this is the assertion that keeps the money findable: the transfer stands, only the payout
        // failed
        static::assertSame('tr_1', $result->providerStageRef);
        static::assertNull($result->providerRef);
    }

    /**
     * A rejected transfer never opened an object: no money has left the platform balance, so there
     * is no stage reference and no payout either.
     */
    public function testATransferRejectionLeavesNoStageReferenceAndSendsNoPayout(): void
    {
        $this->http->answers[self::TRANSFER_PATH] = [400, self::json([
            'error' => ['code' => 'balance_insufficient', 'message' => 'Insufficient funds in the platform balance.'],
        ])];

        $action = new StripeTransferAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        static::assertSame(0, $this->http->count(self::PAYOUT_PATH));

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $result = $model->result(TransferResult::class, 'stripe');
        static::assertSame(TransferResult::STATE_FAILED, $result->state);
        static::assertSame('balance_insufficient', $result->failureCode);
        static::assertNull($result->providerStageRef);
        static::assertNull($result->providerRef);
    }

    public function testTheRecordedRequestCarriesBothStagesAndTheAnswerCarriesTheRawBody(): void
    {
        $this->http->answers[self::TRANSFER_PATH] = [200, self::json(['id' => 'tr_1', 'object' => 'transfer', 'reversed' => false])];
        $this->http->answers[self::PAYOUT_PATH] = [200, self::json(['id' => 'po_1', 'object' => 'payout', 'status' => 'paid'])];

        $action = new StripeTransferAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $recorded = $model->request();
        static::assertArrayHasKey('transfer', $recorded);
        static::assertArrayHasKey('payout', $recorded);

        // the raw answer of the interface rides along - a support case is debugged with it
        static::assertSame('tr_1', $model->response()['transfer']['id']);
        static::assertSame('po_1', $model->response()['payout']['id']);
    }

    public static function payoutStatuses(): iterable
    {
        yield 'pending is the normal course' => [
            'pending',
            TransferResult::STATE_PROCESSING,
            null,
        ];
        yield 'in transit is on its way' => [
            'in_transit',
            TransferResult::STATE_PROCESSING,
            null,
        ];
        yield 'paid is what the interface said last' => [
            'paid',
            TransferResult::STATE_SUCCEEDED,
            null,
        ];
        yield 'failed names its code' => [
            'failed',
            TransferResult::STATE_FAILED,
            'account_closed',
        ];
        yield 'canceled has no code of its own' => [
            'canceled',
            TransferResult::STATE_FAILED,
            'canceled',
        ];
    }

    #[DataProvider('payoutStatuses')]
    public function testThePayoutStatusIsNarrowedToWhatTheTransferMeans(
        string $status,
        string $expectedState,
        ?string $expectedFailureCode,
    ): void {
        $answer = ['id' => 'po_1', 'object' => 'payout', 'status' => $status];

        if ($expectedFailureCode !== null && $status !== 'canceled') {
            $answer['failure_code'] = $expectedFailureCode;
        }

        $result = StripeTransferAction::resultOf('tr_1', $answer);

        static::assertSame($expectedState, $result->state);
        static::assertSame('po_1', $result->providerRef);
        static::assertSame('tr_1', $result->providerStageRef);
        static::assertSame($expectedFailureCode, $result->failureCode);
    }

    public function testAStatusTheActionDoesNotKnowStaysUnknown(): void
    {
        $result = StripeTransferAction::resultOf('tr_1', ['id' => 'po_1', 'status' => 'block_something_new']);

        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
    }

    public function testTheIdempotencyKeyNamesTheOutNumberAndTheStage(): void
    {
        static::assertSame('OUT-1:transfer', StripeTransferAction::idempotencyKey('OUT-1', StripeTransferAction::STAGE_TRANSFER));
        static::assertSame('OUT-1:payout', StripeTransferAction::idempotencyKey('OUT-1', StripeTransferAction::STAGE_PAYOUT));
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function json(array $body): string
    {
        $encoded = json_encode($body);
        \assert(\is_string($encoded));

        return $encoded;
    }

    private function transfer(?string $stageRef = null): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->outNo = 'OUT-1';
        $transfer->provider = 'stripe';
        $transfer->channel = 'stripe_transfer';
        $transfer->amount = 1000;
        $transfer->currency = 'USD';
        $transfer->providerStageRef = $stageRef;

        return $transfer;
    }

    private function recipient(
        bool $payoutEnabled = true,
        string $identityType = 'stripe_account',
        ?string $accountId = 'acct_1',
    ): PaymentTransferRecipientEntity {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'rec-1';
        $recipient->provider = 'stripe';
        $recipient->identityType = $identityType;
        $recipient->providerAccountId = $accountId;
        $recipient->onboardingStatus = 'registered';
        $recipient->payoutEnabled = $payoutEnabled;

        return $recipient;
    }

    private function request(
        bool $payoutEnabled = true,
        string $identityType = 'stripe_account',
        ?string $accountId = 'acct_1',
        ?string $stageRef = null,
    ): PaymentTransferRequest {
        $transfer = $this->transfer($stageRef);

        return new PaymentTransferRequest(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $this->recipient($payoutEnabled, $identityType, $accountId),
        );
    }

    private function api(): StripeApi
    {
        $channel = new PaymentChannel('stripe_transfer', 'stripe', 'stripe_transfer', PaymentFlow::REDIRECT);

        return new StripeApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'stripe',
                    'stripe',
                    [],
                    ['stripe_transfer' => $channel],
                    new PaymentGatewayFactory('stripe', StripeApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: [StripeApi::SECRET_KEY => 'sk_test_unit'],
                params: [],
                runtimeParams: [],
                settings: [],
            ),
            'https://example.com/notify',
            '',
        );
    }
}
