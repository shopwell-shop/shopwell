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
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferAction;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalTransferAction::class)]
class PaypalTransferActionTest extends TestCase
{
    private const PAYOUTS_PATH = '/v1/payments/payouts';

    private const SECRET = 'unit-test-cipher-secret';

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

    public function testSupportsOnlyTransferRequestsWithAPaymentGatewayModel(): void
    {
        $action = new PaypalTransferAction($this->cipher());

        static::assertTrue($action->supports($this->request()));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
    }

    /**
     * The payum contract is easy to break silently: a request still of the right class but whose
     * model was replaced is no transfer the action may execute.
     */
    public function testSupportsRejectsARequestWhoseModelIsNotAPaymentGatewayModel(): void
    {
        $request = $this->request();
        $request->setModel(new \stdClass());

        static::assertFalse((new PaypalTransferAction($this->cipher()))->supports($request));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new PaypalTransferAction($this->cipher());

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new PaypalTransferAction($this->cipher());

        $this->expectExceptionObject(PaymentException::actionWithoutApi(PaypalTransferAction::class));

        $action->execute($this->request());
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new PaypalTransferAction($this->cipher());

        $this->expectExceptionObject(PaymentException::unsupportedApi(PaypalTransferAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public static function identityTypes(): iterable
    {
        yield 'an email is an EMAIL' => ['paypal_email', 'payer@example.com', 'EMAIL'];
        yield 'a phone is a PHONE' => ['paypal_phone', '+15550001111', 'PHONE'];
        yield 'a payer id is a PAYPAL_ID' => ['paypal_payer_id', 'PAYER-1', 'PAYPAL_ID'];
    }

    /**
     * The body of the single call: the identity type decides `recipient_type`, the receiver is the
     * *decrypted* identity, the amount is the decimal string of the interface and both ids are the
     * derived batch id.
     */
    #[DataProvider('identityTypes')]
    public function testExecuteBuildsTheItemOfTheIdentityType(string $identityType, string $plain, string $recipientType): void
    {
        $this->httpAnswer(201, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'PENDING'],
        ]);

        $action = new PaypalTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request(identityType: $identityType, plain: $plain);
        $action->execute($request);

        static::assertCount(1, $this->requests);
        static::assertSame('POST', $this->requests[0]['method']);
        static::assertStringEndsWith(self::PAYOUTS_PATH, $this->requests[0]['url']);

        $body = $this->bodyOf(self::PAYOUTS_PATH);

        static::assertSame('OUT-1', $body['sender_batch_header']['sender_batch_id']);
        static::assertSame('转账', $body['sender_batch_header']['email_subject']);

        $item = $body['items'][0];
        static::assertSame($recipientType, $item['recipient_type']);
        // the ciphertext never leaves the platform - the plain text is what goes out
        static::assertSame($plain, $item['receiver']);
        static::assertSame('USD', $item['amount']['currency']);
        static::assertSame('10.00', $item['amount']['value']);
        static::assertSame(PaypalTransferAction::batchIdOf('OUT-1'), $item['sender_item_id']);
        static::assertSame('转账', $item['note']);

        // the batch id is the idempotency key of the interface for the next 30 days
        static::assertSame('OUT-1', $this->headerOf(self::PAYOUTS_PATH, 'PayPal-Request-Id'));
        static::assertSame('Bearer access-token', $this->headerOf(self::PAYOUTS_PATH, 'Authorization'));
    }

    /**
     * An identity of another interface is refused before a request is built - the call of a batch id
     * must not be burned on a rejection the interface was guaranteed to give.
     */
    public function testExecuteRefusesAnIdentityOfAnotherInterfaceBeforeAnyRequestIsSent(): void
    {
        $this->httpAnswer(201, []);

        $action = new PaypalTransferAction($this->cipher());
        $action->setApi($this->api());

        try {
            $action->execute($this->request(identityType: 'bankcard'));
            static::fail('The transfer was expected to be refused for an identity of another interface.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_IDENTITY_NOT_SUPPORTED, $exception->getErrorCode());
        }

        static::assertSame([], $this->requests);
    }

    public function testExecuteRefusesAnEmptyReceiverBeforeAnyRequestIsSent(): void
    {
        $this->httpAnswer(201, []);

        $action = new PaypalTransferAction($this->cipher());
        $action->setApi($this->api());

        try {
            $action->execute($this->request(plain: '   '));
            static::fail('The transfer was expected to be refused for an empty identity.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_RECIPIENT_INCOMPLETE, $exception->getErrorCode());
        }

        static::assertSame([], $this->requests);
    }

    /**
     * The assertion that protects the money: a batch is `SUCCESS` even when its single item is
     * `UNCLAIMED`, so the submission must never book the transfer as succeeded from it.
     */
    public function testASuccessfulBatchStaysProcessing(): void
    {
        $this->httpAnswer(201, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'SUCCESS'],
            'items' => [['transaction_status' => 'UNCLAIMED']],
        ]);

        $action = new PaypalTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_PROCESSING, $result->state);
        // the batch is the object every query is addressed to - never the item id
        static::assertSame('BATCH-1', $result->providerRef);
        static::assertNull($result->providerStageRef);
        static::assertNull($result->failureCode);
    }

    public static function batchStatuses(): iterable
    {
        yield 'denied is a failure that names itself' => ['DENIED', TransferResult::STATE_FAILED, 'DENIED'];
        yield 'canceled is a failure that names itself' => ['CANCELED', TransferResult::STATE_FAILED, 'CANCELED'];
        yield 'pending is on its way' => ['PENDING', TransferResult::STATE_PROCESSING, null];
        yield 'processing is on its way' => ['PROCESSING', TransferResult::STATE_PROCESSING, null];
        // the money may still sit unclaimed inside the batch - the item decides, not the batch
        yield 'success is no evidence the money arrived' => ['SUCCESS', TransferResult::STATE_PROCESSING, null];
    }

    #[DataProvider('batchStatuses')]
    public function testTheBatchStatusIsNarrowedToWhatTheTransferMeans(
        string $status,
        string $expectedState,
        ?string $expectedFailureCode,
    ): void {
        $result = PaypalTransferAction::resultOf([
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => $status],
        ]);

        static::assertSame($expectedState, $result->state);
        static::assertSame('BATCH-1', $result->providerRef);
        static::assertSame($expectedFailureCode, $result->failureCode);
    }

    public function testAStatusTheActionDoesNotKnowStaysUnknown(): void
    {
        $result = PaypalTransferAction::resultOf([
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'SOMETHING_NEW'],
        ]);

        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
    }

    public function testARejectionOfTheInterfaceFailsTheTransferWithTheCodePreserved(): void
    {
        $this->httpAnswer(422, [
            'name' => 'VALIDATION_ERROR',
            'message' => 'The receiver is invalid.',
        ]);

        $action = new PaypalTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_FAILED, $result->state);
        static::assertSame('VALIDATION_ERROR', $result->failureCode);
        static::assertNull($result->providerRef);
    }

    public function testATransportFailureFailsTheTransfer(): void
    {
        $this->httpFailure(new TransportException('connection reset'));

        $action = new PaypalTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $result = $this->resultOf($request);

        static::assertSame(TransferResult::STATE_FAILED, $result->state);
        static::assertSame('transport', $result->failureCode);
    }

    public function testTheRecordedRequestAndAnswerAreKeptOnTheModel(): void
    {
        $this->httpAnswer(201, [
            'batch_header' => ['payout_batch_id' => 'BATCH-1', 'batch_status' => 'PENDING'],
        ]);

        $action = new PaypalTransferAction($this->cipher());
        $action->setApi($this->api());

        $request = $this->request();
        $action->execute($request);

        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        static::assertSame('OUT-1', $model->request()['sender_batch_header']['sender_batch_id']);
        // the raw answer of the interface rides along - a support case is debugged with it
        static::assertSame('BATCH-1', $model->response()['batch_header']['payout_batch_id']);
    }

    public function testBatchIdOfKeepsAnOutNumberOfThirtyCharacters(): void
    {
        $outNo = str_repeat('a', 30);

        static::assertSame($outNo, PaypalTransferAction::batchIdOf($outNo));
    }

    /**
     * Two out numbers that share their first 31 characters must not collapse onto one batch - paypal
     * would answer "duplicate batch" and the platform would read the money of the other transfer as
     * its own retry.
     */
    public function testBatchIdOfShortensLongOutNumbersDeterministicallyAndWithoutCollisions(): void
    {
        $prefix = str_repeat('a', 31);
        $first = $prefix . 'b';
        $second = $prefix . 'c';

        $batchId = PaypalTransferAction::batchIdOf($first);

        static::assertSame(30, \strlen($batchId));
        // the out number stays readable, the hash keeps the tail of the out number apart
        static::assertStringStartsWith(substr($first, 0, 22), $batchId);
        static::assertNotSame($first, $batchId);
        // the derivation is reproducible from the out number alone - the query and the webhook
        // recompute it
        static::assertSame($batchId, PaypalTransferAction::batchIdOf($first));
        static::assertNotSame($batchId, PaypalTransferAction::batchIdOf($second));
    }

    private function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher(self::SECRET);
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

    private function httpFailure(\Throwable $failure): void
    {
        $this->http = new MockHttpClient(function (string $method, string $url, array $options = []) use ($failure): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            throw $failure;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyOf(string $path): array
    {
        foreach ($this->requests as $request) {
            if (str_ends_with($request['url'], $path)) {
                $body = $request['options']['body'] ?? '';

                static::assertIsString($body);

                $decoded = json_decode($body, true);
                static::assertIsArray($decoded);

                return $decoded;
            }
        }

        static::fail(\sprintf('no request was made to %s', $path));
    }

    private function headerOf(string $path, string $name): ?string
    {
        foreach ($this->requests as $request) {
            if (!str_ends_with($request['url'], $path)) {
                continue;
            }

            $headers = $request['options']['headers'] ?? [];

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

        static::fail(\sprintf('no request was made to %s', $path));
    }

    private function resultOf(PaymentTransferRequest $request): TransferResult
    {
        $model = $request->getModel();
        static::assertInstanceOf(PaymentGatewayModel::class, $model);

        return $model->result(TransferResult::class, 'paypal');
    }

    private function transfer(string $outNo = 'OUT-1'): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->outNo = $outNo;
        $transfer->provider = 'paypal';
        $transfer->channel = 'paypal_payout';
        $transfer->amount = 1000;
        $transfer->currency = 'USD';

        return $transfer;
    }

    private function recipient(string $identityType, string $plain): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'rec-1';
        $recipient->provider = 'paypal';
        $recipient->identityType = $identityType;
        $recipient->identityValue = $this->cipher()->encryptValue($plain);
        $recipient->onboardingStatus = 'registered';
        $recipient->payoutEnabled = true;

        return $recipient;
    }

    private function request(
        string $identityType = 'paypal_email',
        string $plain = 'payer@example.com',
    ): PaymentTransferRequest {
        $transfer = $this->transfer();

        return new PaymentTransferRequest(
            PaymentGatewayModel::forTransfer($transfer),
            $transfer,
            $this->recipient($identityType, $plain),
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
