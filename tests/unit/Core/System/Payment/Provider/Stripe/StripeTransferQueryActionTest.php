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
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeTransferQueryAction;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Stripe\ApiRequestor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeTransferQueryAction::class)]
class StripeTransferQueryActionTest extends TestCase
{
    private const PAYOUT_PATH = '/v1/payouts/po_1';

    private FakeStripeHttpClient $http;

    protected function setUp(): void
    {
        // the sdk of stripe talks through a global http client - the fake below intercepts every
        // request before it would leave the process, and records it for the assertions
        $this->http = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->http);
    }

    public function testSupportsOnlyTransferQueriesWithAPaymentGatewayModel(): void
    {
        $action = new StripeTransferQueryAction();

        static::assertTrue($action->supports($this->request()));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new StripeTransferQueryAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new StripeTransferQueryAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(StripeTransferQueryAction::class));

        $action->execute($this->request());
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new StripeTransferQueryAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(StripeTransferQueryAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    /**
     * A transfer whose payout never opened an object is nothing the interface could be asked about -
     * the query answers unknown without a call.
     */
    public function testAQueryWithoutAProviderRefAsksNothingAndStaysUnknown(): void
    {
        $action = new StripeTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request(providerRef: null);
        $action->execute($request);

        static::assertSame(0, $this->http->count(self::PAYOUT_PATH));

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $result = $model->result(TransferQueryResult::class, 'stripe');
        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
    }

    /**
     * The query is made on behalf of the connected account - without the header stripe would look
     * for the payout on the platform account and answer that it does not know it.
     */
    public function testAQueryRidesTheAccountOfTheRecipient(): void
    {
        $this->http->answers[self::PAYOUT_PATH] = [200, self::json([
            'id' => 'po_1',
            'object' => 'payout',
            'status' => 'in_transit',
        ])];

        $action = new StripeTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        static::assertSame(1, $this->http->count(self::PAYOUT_PATH));
        static::assertSame('get', $this->http->requestOf(self::PAYOUT_PATH)['method']);
        static::assertSame('acct_1', $this->http->headerOf(self::PAYOUT_PATH, 'Stripe-Account'));

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $result = $model->result(TransferQueryResult::class, 'stripe');
        static::assertSame(TransferResult::STATE_PROCESSING, $result->state);
        // the original string is kept - a support case is debugged with it
        static::assertSame('in_transit', $result->providerStatus);
        static::assertSame('po_1', $result->providerRef);
        static::assertNull($result->failureCode);
    }

    public function testAQueryRefusesARecipientWithoutAConnectedAccountId(): void
    {
        $action = new StripeTransferQueryAction();
        $action->setApi($this->api());

        $this->expectExceptionObject(PaymentException::transferRecipientIncomplete('provider_account_id'));

        $action->execute($this->request(accountId: null));
    }

    /**
     * A rejected query is no answer about the payout at all: it stays unknown, the next query
     * decides.
     */
    public function testARejectedQueryStaysUnknown(): void
    {
        $this->http->answers[self::PAYOUT_PATH] = [404, self::json([
            'error' => ['code' => 'resource_missing', 'message' => 'No such payout: po_1'],
        ])];

        $action = new StripeTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $result = $model->result(TransferQueryResult::class, 'stripe');
        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
        static::assertSame('resource_missing', $result->providerStatus);
    }

    public static function payoutStatuses(): iterable
    {
        yield 'pending is on its way' => ['pending', TransferResult::STATE_PROCESSING];
        yield 'in transit is on its way' => ['in_transit', TransferResult::STATE_PROCESSING];
        yield 'paid is what the interface said last' => ['paid', TransferResult::STATE_SUCCEEDED];
        yield 'failed is final for the moment' => ['failed', TransferResult::STATE_FAILED];
        // `TransferResult` has no state of its own for a canceled payout - the money never left the
        // connected account and never will, which is what failed means for the caller
        yield 'canceled is a failure that names its reason' => ['canceled', TransferResult::STATE_FAILED];
    }

    #[DataProvider('payoutStatuses')]
    public function testThePayoutStatusIsNarrowedToWhatTheTransferMeans(string $status, string $expectedState): void
    {
        $this->http->answers[self::PAYOUT_PATH] = [200, self::json([
            'id' => 'po_1',
            'object' => 'payout',
            'status' => $status,
            'failure_code' => 'account_closed',
        ])];

        $action = new StripeTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        $result = $model->result(TransferQueryResult::class, 'stripe');
        static::assertSame($expectedState, $result->state);
        static::assertSame($status, $result->providerStatus);

        $expectedFailureCode = $status === 'canceled'
            ? 'canceled'
            : ($expectedState === TransferResult::STATE_FAILED ? 'account_closed' : null);
        static::assertSame($expectedFailureCode, $result->failureCode);
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

    private function transfer(?string $providerRef = 'po_1'): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->outNo = 'OUT-1';
        $transfer->provider = 'stripe';
        $transfer->channel = 'stripe_transfer';
        $transfer->amount = 1000;
        $transfer->currency = 'USD';
        $transfer->providerRef = $providerRef;
        $transfer->providerStageRef = 'tr_1';

        return $transfer;
    }

    private function recipient(?string $accountId = 'acct_1'): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'rec-1';
        $recipient->provider = 'stripe';
        $recipient->identityType = 'stripe_account';
        $recipient->providerAccountId = $accountId;
        $recipient->onboardingStatus = 'registered';
        $recipient->payoutEnabled = true;

        return $recipient;
    }

    private function request(?string $providerRef = 'po_1', ?string $accountId = 'acct_1'): PaymentTransferQuery
    {
        $transfer = $this->transfer($providerRef);

        return new PaymentTransferQuery(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $this->recipient($accountId),
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
