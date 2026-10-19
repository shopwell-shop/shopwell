<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Models\OAuthToken;
use PaypalServerSdkLib\PaypalServerSdkClient;
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
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferQueryAction;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalTransferQueryAction::class)]
class PaypalTransferQueryActionTest extends TestCase
{
    private const BATCH_PATH = '/v1/payments/payouts/BATCH-1';

    /**
     * @var list<array{method: string, url: string, options: array<string, mixed>}>
     */
    private array $requests = [];

    /**
     * The symfony client the api of paypal is handed over - the fake below intercepts every request
     * before it would leave the process, and records it for the assertions.
     */
    private HttpClientInterface $http;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient([]);
    }

    public function testSupportsOnlyTransferQueriesWithAPaymentGatewayModel(): void
    {
        $action = new PaypalTransferQueryAction();

        static::assertTrue($action->supports($this->request()));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
    }

    public function testSupportsRejectsARequestWhoseModelIsNotAPaymentGatewayModel(): void
    {
        $request = $this->request();
        $request->setModel(new \stdClass());

        static::assertFalse((new PaypalTransferQueryAction())->supports($request));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new PaypalTransferQueryAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalTransferQueryAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(PaypalTransferQueryAction::class));

        $action->execute($this->request());
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new PaypalTransferQueryAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(PaypalTransferQueryAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    /**
     * A transfer whose payout never opened a batch is nothing the interface could be asked about -
     * the query answers unknown without a call.
     */
    public function testAQueryWithoutAProviderRefAsksNothingAndStaysUnknown(): void
    {
        $action = new PaypalTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request(providerRef: null);
        $action->execute($request);

        static::assertSame([], $this->requests);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
    }

    public function testAQueryAsksForTheBatchOfTheTransfer(): void
    {
        $this->httpAnswer(200, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'PENDING'],
        ]);

        $action = new PaypalTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        static::assertCount(1, $this->requests);
        static::assertSame('GET', $this->requests[0]['method']);
        static::assertStringEndsWith(self::BATCH_PATH, $this->requests[0]['url']);
        static::assertSame('Bearer access-token', $this->headerOf('Authorization'));
    }

    /**
     * The assertion that protects the money: the item is the truth, the batch is only a fallback. A
     * `UNCLAIMED` item inside a `SUCCESS` batch has not arrived - the money is unclaimed for 30 days
     * and then returns.
     */
    public function testAnUnclaimedItemWinsOverASuccessfulBatch(): void
    {
        $this->httpAnswer(200, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'SUCCESS'],
            'items' => [['transaction_status' => 'UNCLAIMED', 'payout_item_id' => 'ITEM-1']],
        ]);

        $action = new PaypalTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_PROCESSING, $result->state);
        static::assertSame('UNCLAIMED', $result->providerStatus);
        static::assertSame('BATCH-1', $result->providerRef);
        static::assertNull($result->failureCode);
    }

    public static function itemStatuses(): iterable
    {
        yield 'success is the money arrived' => ['SUCCESS', TransferResult::STATE_SUCCEEDED, null];
        yield 'failed names its error' => ['FAILED', TransferResult::STATE_FAILED, 'RECEIVER_UNREGISTERED'];
        yield 'blocked is a failure as well' => ['BLOCKED', TransferResult::STATE_FAILED, 'RECEIVER_UNREGISTERED'];
        yield 'pending is on its way' => ['PENDING', TransferResult::STATE_PROCESSING, null];
        yield 'unclaimed is not arrived' => ['UNCLAIMED', TransferResult::STATE_PROCESSING, null];
        yield 'on hold is not arrived either' => ['ONHOLD', TransferResult::STATE_PROCESSING, null];
        yield 'returned came back' => ['RETURNED', TransferResult::STATE_RETURNED, 'RECEIVER_UNREGISTERED'];
        yield 'refunded came back' => ['REFUNDED', TransferResult::STATE_RETURNED, 'RECEIVER_UNREGISTERED'];
        yield 'reversed came back' => ['REVERSED', TransferResult::STATE_RETURNED, 'RECEIVER_UNREGISTERED'];
    }

    /**
     * Without an error of the item the status itself is the only evidence left.
     */
    public function testAReturnedItemWithoutAnErrorNamesItsStatus(): void
    {
        $this->httpAnswer(200, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'SUCCESS'],
            'items' => [['transaction_status' => 'RETURNED']],
        ]);

        $action = new PaypalTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_RETURNED, $result->state);
        static::assertSame('RETURNED', $result->failureCode);
    }

    #[DataProvider('itemStatuses')]
    public function testTheItemStatusIsNarrowedToWhatTheTransferMeans(
        string $status,
        string $expectedState,
        ?string $expectedFailureCode,
    ): void {
        $this->httpAnswer(200, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'SUCCESS'],
            'items' => [[
                'transaction_status' => $status,
                'errors' => [['name' => 'RECEIVER_UNREGISTERED', 'message' => 'The receiver is not registered.']],
            ]],
        ]);

        $action = new PaypalTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame($expectedState, $result->state);
        static::assertSame($status, $result->providerStatus);
        static::assertSame($expectedFailureCode, $result->failureCode);
    }

    public static function batchStatuses(): iterable
    {
        yield 'a pending batch is on its way' => ['PENDING', TransferResult::STATE_PROCESSING, null];
        yield 'a processing batch is on its way' => ['PROCESSING', TransferResult::STATE_PROCESSING, null];
        yield 'a denied batch failed' => ['DENIED', TransferResult::STATE_FAILED, 'DENIED'];
        yield 'a canceled batch failed' => ['CANCELED', TransferResult::STATE_FAILED, 'CANCELED'];
    }

    /**
     * An answer without items leaves only the batch status to read.
     */
    #[DataProvider('batchStatuses')]
    public function testAnAnswerWithoutItemsFallsBackToTheBatchStatus(
        string $status,
        string $expectedState,
        ?string $expectedFailureCode,
    ): void {
        $this->httpAnswer(200, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => $status],
            'items' => [],
        ]);

        $action = new PaypalTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame($expectedState, $result->state);
        static::assertSame($status, $result->providerStatus);
        static::assertSame($expectedFailureCode, $result->failureCode);
    }

    public function testAnItemStatusTheActionDoesNotKnowStaysUnknown(): void
    {
        $this->httpAnswer(200, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'SUCCESS'],
            'items' => [['transaction_status' => 'SOMETHING_NEW']],
        ]);

        $action = new PaypalTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        static::assertSame(TransferResult::STATE_UNKNOWN, $this->resultOf($request)->state);
    }

    /**
     * A rejected query is no answer about the payout at all: it stays unknown, the next query
     * decides.
     */
    public function testARejectedQueryStaysUnknown(): void
    {
        $this->httpAnswer(401, [
            'name' => 'AUTHENTICATION_FAILURE',
            'message' => 'Authentication failed due to invalid authentication credentials.',
        ]);

        $action = new PaypalTransferQueryAction();
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
        static::assertSame('AUTHENTICATION_FAILURE', $result->providerStatus);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function httpAnswer(int $status, array $body): void
    {
        $encoded = json_encode($body);
        \assert(\is_string($encoded));

        $this->http = new MockHttpClient(function (string $method, string $url, array $options = []) use ($status, $encoded): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse($encoded, ['http_code' => $status]);
        });
    }

    private function headerOf(string $name): ?string
    {
        $headers = $this->requests[0]['options']['headers'] ?? [];

        foreach ($headers as $key => $value) {
            if (\is_string($key) && strtolower($key) === strtolower($name)) {
                return (string) $value;
            }

            if (!\is_string($value)) {
                continue;
            }

            [$headerName, $headerValue] = array_pad(explode(':', $value, 2), 2, '');

            if (strtolower(trim($headerName)) === strtolower($name)) {
                return trim($headerValue);
            }
        }

        return null;
    }

    private function resultOf(PaymentTransferQuery $request): TransferQueryResult
    {
        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        return $model->result(TransferQueryResult::class, 'paypal');
    }

    private function transfer(?string $providerRef = 'BATCH-1'): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->outNo = 'OUT-1';
        $transfer->provider = 'paypal';
        $transfer->channel = 'paypal_payout';
        $transfer->amount = 1000;
        $transfer->currency = 'USD';
        $transfer->providerRef = $providerRef;

        return $transfer;
    }

    private function recipient(): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'rec-1';
        $recipient->provider = 'paypal';
        $recipient->identityType = 'paypal_email';
        $recipient->identityValue = (new PaymentCredentialCipher('unit-test-cipher-secret'))->encryptValue('payer@example.com');
        $recipient->onboardingStatus = 'registered';
        $recipient->payoutEnabled = true;

        return $recipient;
    }

    private function request(?string $providerRef = 'BATCH-1'): PaymentTransferQuery
    {
        $transfer = $this->transfer($providerRef);

        return new PaymentTransferQuery(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $this->recipient(),
        );
    }

    private function api(): PaypalApi
    {
        $channel = new PaymentChannel('paypal_payout', 'paypal', 'paypal_payout', PaymentFlow::REDIRECT);

        return new PaypalApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'paypal',
                    'paypal',
                    [],
                    ['paypal_payout' => $channel],
                    new PaymentGatewayFactory('paypal', PaypalApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: [PaypalApi::CLIENT_ID => 'client-id', PaypalApi::CLIENT_SECRET => 'client-secret'],
                params: [],
                runtimeParams: [],
                settings: [],
                mode: 'own',
            ),
            'https://example.com/notify',
            '',
            $this->http,
            // a client that already carries a token, so the token is never fetched over the network
            $this->sdkClient(),
        );
    }

    private function sdkClient(): PaypalServerSdkClient
    {
        $token = new OAuthToken('access-token', 'Bearer');
        $token->setExpiry(time() + 3600);

        return new PaypalServerSdkClient([
            'environment' => Environment::SANDBOX,
            'oAuthClientId' => 'client-id',
            'oAuthClientSecret' => 'client-secret',
            'oAuthToken' => $token,
        ]);
    }
}
