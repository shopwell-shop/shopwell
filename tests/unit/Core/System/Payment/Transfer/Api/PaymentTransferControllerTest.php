<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\Api;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\Request\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferController;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferSubmitter;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Account\_fixtures\PaymentAccountLockStub;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Shopwell\Tests\Unit\Core\System\Payment\Transfer\PaymentTransferRulesFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The administration endpoints of a payout.
 *
 * The money critical knowledge of the controller is its truth table: which observation of a submission
 * means "the channel took it", "the channel refused it" or "the platform does not know". That table is a
 * pure static function on purpose - `PaymentTransferService` and `PaymentTransferSubmitter` are final and
 * can not be doubled, so a test that had to build them would test the wiring instead of the decision.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferController::class)]
class PaymentTransferControllerTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    private const NOW = '2026-10-21T12:00:00+00:00';

    private const ROUTE_ACL = [
        'options' => ['api.action.payment.transfer.options', '/api/_action/payment/transfers/options', Request::METHOD_GET, 'payment_transfer:create'],
        'recipients' => ['api.action.payment.transfer.recipients', '/api/_action/payment/transfers/recipients', Request::METHOD_GET, 'payment_transfer:create'],
        'create' => ['api.action.payment.transfer.create', '/api/_action/payment/transfers', Request::METHOD_POST, 'payment_transfer:create'],
        'submit' => ['api.action.payment.transfer.submit', '/api/_action/payment/transfers/{id}/submit', Request::METHOD_POST, 'payment_transfer:update'],
        'cancel' => ['api.action.payment.transfer.cancel', '/api/_action/payment/transfers/{id}/cancel', Request::METHOD_POST, 'payment_transfer:update'],
    ];

    /**
     * A channel that answered success: the row is booked and the money is on its way.
     */
    public function testAChannelAnswerIsAccepted(): void
    {
        static::assertSame(
            PaymentTransferController::OUTCOME_ACCEPTED,
            PaymentTransferController::classifySubmitOutcome(
                new TransferResult(TransferResult::STATE_PROCESSING, 'order-1'),
                $this->transfer(),
                $this->transfer(['state' => PaymentTransferStates::STATE_PROCESSING]),
                null
            )
        );
    }

    /**
     * The channel answered, and the answer was a refusal that is now on the row.
     */
    public function testARefusedChannelAnswerIsReportedAsFailed(): void
    {
        static::assertSame(
            PaymentTransferController::OUTCOME_FAILED,
            PaymentTransferController::classifySubmitOutcome(
                new TransferResult(TransferResult::STATE_FAILED, failureCode: 'PAYEE_NOT_EXIST'),
                $this->transfer(),
                $this->transfer(['state' => PaymentTransferStates::STATE_FAILED]),
                null
            )
        );
    }

    /**
     * A channel answer the platform can not book (an unclassifiable state without an object reference) is
     * not an acceptance: nothing moved as far as the platform knows.
     */
    public function testAnAnswerThatBooksNothingIsUnknown(): void
    {
        static::assertSame(
            PaymentTransferController::OUTCOME_UNKNOWN,
            PaymentTransferController::classifySubmitOutcome(
                new TransferResult(TransferResult::STATE_UNKNOWN),
                $this->transfer(),
                $this->transfer(),
                null
            )
        );
    }

    /**
     * The guard refused the submission before a gateway was built - the transfer is not submittable at all.
     */
    public function testASubmissionOfANonCreatedTransferIsRejected(): void
    {
        static::assertSame(
            PaymentTransferController::OUTCOME_REJECTED,
            PaymentTransferController::classifySubmitOutcome(
                null,
                $this->transfer(['state' => PaymentTransferStates::STATE_SUBMITTED]),
                $this->transfer(['state' => PaymentTransferStates::STATE_SUBMITTED]),
                PaymentTransferException::transferAlreadyFinalized('TF-1', 'submitted')
            )
        );
    }

    /**
     * An exception after the state machine already moved on happened after the money was handed over.
     */
    public function testAnExceptionAfterTheStateMovedOnIsAccepted(): void
    {
        static::assertSame(
            PaymentTransferController::OUTCOME_ACCEPTED,
            PaymentTransferController::classifySubmitOutcome(
                null,
                $this->transfer(),
                $this->transfer(['state' => PaymentTransferStates::STATE_SUBMITTED, 'submitAttempts' => 1]),
                PaymentGatewayException::gatewayError('alipay_transfer', 'the channel answered nothing')
            )
        );
    }

    /**
     * The one case that must never be reported as a failure: the call did not finish, and the only durable
     * evidence that something may have left the platform is the attempt counter.
     */
    public function testAnExceptionWithARecordedAttemptIsUnknown(): void
    {
        static::assertSame(
            PaymentTransferController::OUTCOME_UNKNOWN,
            PaymentTransferController::classifySubmitOutcome(
                null,
                $this->transfer(['submitAttempts' => 0]),
                $this->transfer(['submitAttempts' => 1]),
                PaymentGatewayException::gatewayError('alipay_transfer', 'timeout')
            )
        );
    }

    /**
     * An exception without a recorded attempt provably never reached the channel - a missing parameter, an
     * interface without a payout capability, a payout of another app.
     */
    public function testAnExceptionWithoutARecordedAttemptIsRejected(): void
    {
        static::assertSame(
            PaymentTransferController::OUTCOME_REJECTED,
            PaymentTransferController::classifySubmitOutcome(
                null,
                $this->transfer(['submitAttempts' => 2]),
                $this->transfer(['submitAttempts' => 2]),
                PaymentException::missingParameter('transferSceneName')
            )
        );
    }

    /**
     * Without an exception and without an answer nothing can be said about the money.
     */
    public function testNeitherAnAnswerNorAnExceptionIsUnknown(): void
    {
        static::assertSame(
            PaymentTransferController::OUTCOME_UNKNOWN,
            PaymentTransferController::classifySubmitOutcome(null, $this->transfer(), $this->transfer(), null)
        );
    }

    /**
     * Every route is an administration route with its own privilege, and the two write routes are the ones
     * that move money - a missing ACL there would be the whole feature.
     *
     * @param list<string> $expected
     */
    #[DataProvider('routes')]
    public function testEveryRouteDeclaresItsScopeAndItsPrivilege(
        string $method,
        string $name,
        string $path,
        string $httpMethod,
        array $expected,
    ): void {
        $route = $this->route($method);

        static::assertSame($name, $route->name);
        static::assertSame($path, $route->path);
        static::assertSame([$httpMethod], $route->methods);

        $defaults = $route->defaults;

        static::assertTrue($defaults['auth_required']);
        static::assertSame(['administration'], $defaults[PlatformRequest::ATTRIBUTE_ROUTE_SCOPE]);
        static::assertSame($expected, $defaults[PlatformRequest::ATTRIBUTE_ACL]);
    }

    /**
     * @return iterable<string, array{string, string, string, string, list<string>}>
     */
    public static function routes(): iterable
    {
        foreach (self::ROUTE_ACL as $method => [$name, $path, $httpMethod, $acl]) {
            yield $method => [$method, $name, $path, $httpMethod, [$acl]];
        }
    }

    /**
     * The single most important protocol decision of this feature: the submit endpoint does not take a
     * body, so it has no way to name an app. The app of a payout is `transfer.paymentAppId` and nothing
     * else, which is what makes "pay out of another merchant account" unwritable instead of merely
     * unlikely.
     */
    public function testTheSubmitEndpointTakesNoPayload(): void
    {
        $parameters = (new \ReflectionMethod(PaymentTransferController::class, 'submit'))->getParameters();

        static::assertCount(1, $parameters);
        static::assertSame('id', $parameters[0]->getName());
    }

    /**
     * A rejection without a reason is refused before anything is read or written: the reason is the only
     * audit trail of a rejected payout.
     */
    public function testACancelWithoutAReasonIsRefused(): void
    {
        $controller = $this->controller();

        try {
            $controller->cancel('transfer-id', new Request());
            static::fail('a cancel without a reason has to be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::MISSING_PARAMETER, $exception->getErrorCode());
        }
    }

    /**
     * Submitting a payout that is no longer `created` is a conflict, not a second payout - and it is
     * refused before any channel is built.
     */
    public function testSubmittingAFinalizedTransferIsAConflict(): void
    {
        $transfer = $this->transfer(['state' => PaymentTransferStates::STATE_SUBMITTED]);
        $controller = $this->controller($transfer, $this->app());

        try {
            $controller->submit('transfer-id');
            static::fail('a finalized transfer must not be submitted again');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_ALREADY_FINALIZED, $exception->getErrorCode());
        }
    }

    /**
     * The answer that arrives but can not be booked is the observation the endpoint must never turn into a
     * 502: the channel answered, so the money may already be on its way, and a 5xx is exactly the
     * invitation to send the payout a second time.
     *
     * The endpoint has to answer 200 with `unknown`, and the row has to say that an attempt happened -
     * otherwise `classifySubmitOutcome()` would read the exception as a rejection.
     *
     * The stub repository feeds its writes back into its reads, so the assertion below fails if the attempt
     * is not recorded: without it the endpoint classifies an unchanged counter and answers with the error
     * envelope of the exception instead of `unknown`.
     */
    public function testAnAnswerThatCanNotBeBookedIsAnsweredAsUnknown(): void
    {
        $response = $this->controllerWithAChannelAnsweringWithoutAnObject()->submit('transfer-id');

        static::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(PaymentTransferController::OUTCOME_UNKNOWN, $payload['outcome']);
        // the message is the instruction the operator has to follow before anything is retried
        static::assertStringContainsString('check the out number at the channel', (string) $payload['message']);

        /** @var array<string, mixed> $transfer */
        $transfer = $payload['transfer'];

        // the money may have moved, so nothing terminal was booked - and the attempt is the evidence
        static::assertSame(1, $transfer['submitAttempts']);
        static::assertSame(PaymentTransferStates::STATE_CREATED, $transfer['state']['technicalName']);
        static::assertStringContainsString('without an object reference', (string) $transfer['failureReason']);
    }

    private function route(string $method): Route
    {
        $attributes = (new \ReflectionMethod(PaymentTransferController::class, $method))->getAttributes(Route::class);

        static::assertCount(1, $attributes);

        return $attributes[0]->newInstance();
    }

    /**
     * Assembles the controller with real services and stub repositories - the submitter and the transfer
     * service are final and can not be doubled, so the wiring is the real one and only the database is a
     * stub.
     */
    private function controller(?PaymentTransferEntity $transfer = null, ?PaymentAppEntity $app = null): PaymentTransferController
    {
        $cipher = new PaymentCredentialCipher(self::SECRET);
        $clock = new MockClock(self::NOW);
        $providerRegistry = new PaymentProviderRegistry([]);

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appSearchResult($app));

        $recipientRepository = static::createStub(EntityRepository::class);
        $recipientRepository->method('search')->willReturn($this->recipientSearchResult());

        $transferRepository = static::createStub(EntityRepository::class);
        $transferRepository->method('search')->willReturn($this->transferSearchResult($transfer));

        $stateMachineRegistry = static::createStub(StateMachineRegistry::class);

        $recipientService = new PaymentTransferRecipientService($recipientRepository, $cipher, $clock, $providerRegistry);
        $transferService = new PaymentTransferService(
            $transferRepository,
            $recipientRepository,
            new PaymentTransferStateHandler($stateMachineRegistry),
            $recipientService,
            $cipher,
            $this->initialStateIdLoader(),
            $clock,
            $providerRegistry,
            NumberRangeFixture::generator('PT1001'),
            PaymentTransferRulesFixture::resolvedConfig(),
            new PaymentAccountLockStub(),
        );

        $configResolver = static::createStub(PaymentConfigResolver::class);

        return new PaymentTransferController(
            $appRepository,
            $recipientRepository,
            new PaymentTransferBooking(
                $appRepository,
                $providerRegistry,
                $recipientService,
                $transferService,
                $clock,
            ),
            new PaymentTransferSubmitter(
                $configResolver,
                $transferService,
                $this->resultBooker($transferService),
            ),
            $transferService,
            new PaymentTransferPresenter($cipher),
            $providerRegistry,
            $this->resultBooker($transferService),
        );
    }

    /**
     * A controller whose interface does answer, but answers something that can not be booked: it reports
     * `processing` and names no object, so `assertObjectReference()` refuses the answer after the request
     * has already reached the channel.
     *
     * The row is read three times and answers differently each time, which is what the flow does: the row
     * before the submission, the row the submitter re-reads to count its attempt, and the row after - the
     * one that carries the attempt and is classified by the endpoint.
     */
    private function controllerWithAChannelAnsweringWithoutAnObject(): PaymentTransferController
    {
        $cipher = new PaymentCredentialCipher(self::SECRET);
        $clock = new MockClock(self::NOW);

        $channel = new PaymentProduct(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            method: 'alipay',
            label: 'Alipay H5',
            interactions: [PaymentInteraction::REDIRECT],
        );

        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (PaymentTransferRequest $request): void {
            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);

            $model->offsetSet(PaymentGatewayModel::RESPONSE, []);
            $model->offsetSet(PaymentGatewayModel::RESULT, new TransferResult(TransferResult::STATE_PROCESSING));
        });

        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            products: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: [PaymentCapability::CAPTURE, PaymentCapability::TRANSFER],
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $app = new PaymentAppEntity();
        $app->id = 'app-id';
        $app->technicalName = 'demo';
        $app->name = 'Demo';
        // an app that collects on an account of its own - the platform of the shop is the other case and
        // would resolve the credentials on the platform level
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->active = true;
        $app->setUniqueIdentifier('app-id');

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appSearchResult($app));

        $recipientRepository = static::createStub(EntityRepository::class);
        $recipientRepository->method('search')->willReturn($this->recipientSearchResult($this->recipient()));

        // what the row carries that a submission can write. The writes below are fed back into every
        // later read, so the row the endpoint classifies is the row the submitter actually booked - a
        // hard coded `submitAttempts` here would make the assertion below true even with the booking of
        // the attempt removed, which is the opposite of what this test is for
        $booked = ['submitAttempts' => 0, 'failureReason' => null];

        $rowOf = function () use (&$booked): PaymentTransferEntity {
            return $this->transfer(array_merge([
                'channel' => 'alipay_h5',
                'channelParams' => ['transfer_scene_name' => '佣金报酬'],
            ], $booked));
        };

        $transferRepository = static::createStub(EntityRepository::class);
        $transferRepository->method('search')->willReturnCallback(function () use ($rowOf): EntitySearchResult {
            // a new instance per read: the endpoint compares the counter of the row it read before the
            // submission with the one of the row it reads afterwards, and handing out the same object
            // twice would compare the counter with itself
            $collection = new PaymentTransferCollection([$rowOf()]);

            return new EntitySearchResult(
                PaymentTransferEntity::ENTITY_NAME,
                $collection->count(),
                $collection,
                null,
                new Criteria(),
                Context::createDefaultContext()
            );
        });
        $transferRepository->method('update')->willReturnCallback(function (array $data) use (&$booked): EntityWrittenContainerEvent {
            foreach (['submitAttempts', 'failureReason'] as $field) {
                if (\array_key_exists($field, $data[0])) {
                    $booked[$field] = $data[0][$field];
                }
            }

            return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
        });

        $stateMachineRegistry = static::createStub(StateMachineRegistry::class);
        $stateMachineRegistry->method('transition')->willReturn(new StateMachineStateCollection());

        $recipientService = new PaymentTransferRecipientService($recipientRepository, $cipher, $clock, $providerRegistry);
        $transferService = new PaymentTransferService(
            $transferRepository,
            $recipientRepository,
            new PaymentTransferStateHandler($stateMachineRegistry),
            $recipientService,
            $cipher,
            $this->initialStateIdLoader(),
            $clock,
            $providerRegistry,
            NumberRangeFixture::generator('PT1001'),
            PaymentTransferRulesFixture::resolvedConfig(),
            new PaymentAccountLockStub(),
        );

        $configResolver = PaymentTransferRulesFixture::boundResolver($provider, $cipher);

        return new PaymentTransferController(
            $appRepository,
            $recipientRepository,
            new PaymentTransferBooking(
                $appRepository,
                $providerRegistry,
                $recipientService,
                $transferService,
                $clock,
            ),
            new PaymentTransferSubmitter(
                $configResolver,
                $transferService,
                $this->resultBooker($transferService),
            ),
            $transferService,
            new PaymentTransferPresenter($cipher),
            $providerRegistry,
            $this->resultBooker($transferService),
        );
    }

    private function resultBooker(PaymentTransferService $transferService): PaymentTransferResultBooker
    {
        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $connection->method('transactional')->willReturnCallback(
            static fn (\Closure $closure): mixed => $closure($connection)
        );

        return new PaymentTransferResultBooker($transferService, $connection);
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn('state-created');

        return $loader;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function transfer(array $overrides = []): PaymentTransferEntity
    {
        $state = $overrides['state'] ?? PaymentTransferStates::STATE_CREATED;
        unset($overrides['state']);

        $stateEntity = new StateMachineStateEntity();
        $stateEntity->setUniqueIdentifier($state);
        $stateEntity->setTechnicalName($state);

        $transfer = new PaymentTransferEntity();
        $transfer->id = str_repeat('a', 32);
        $transfer->paymentAppId = 'app-id';
        $transfer->accountId = 'account-id';
        $transfer->accountId = 'account-id';
        $transfer->transferNo = 'PT2610211200ABCDEF0123';
        $transfer->outNo = 'TF2610211200A1B2';
        $transfer->recipientId = 'recipient-id';
        $transfer->provider = 'alipay';
        $transfer->channel = 'alipay_transfer';
        $transfer->amount = 10;
        $transfer->currency = 'CNY';
        $transfer->statusId = str_repeat('b', 32);
        $transfer->submitAttempts = 0;
        $transfer->statusMachineState = $stateEntity;
        $transfer->assign($overrides);
        $transfer->setUniqueIdentifier('transfer-id');

        return $transfer;
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = 'app-id';
        $app->technicalName = 'platform';
        $app->name = 'Platform';
        $app->type = PaymentAppEntity::TYPE_PLATFORM;
        $app->active = true;
        $app->setUniqueIdentifier('app-id');

        return $app;
    }

    /**
     * @return EntitySearchResult<PaymentTransferCollection>
     */
    private function transferSearchResult(?PaymentTransferEntity $transfer): EntitySearchResult
    {
        $collection = new PaymentTransferCollection($transfer === null ? [] : [$transfer]);

        return new EntitySearchResult(
            PaymentTransferEntity::ENTITY_NAME,
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<PaymentAppCollection>
     */
    private function appSearchResult(?PaymentAppEntity $app): EntitySearchResult
    {
        $collection = new PaymentAppCollection($app === null ? [] : [$app]);

        return new EntitySearchResult(
            'payment_app',
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<PaymentTransferRecipientCollection>
     */
    private function recipientSearchResult(?PaymentTransferRecipientEntity $recipient = null): EntitySearchResult
    {
        $collection = new PaymentTransferRecipientCollection($recipient === null ? [] : [$recipient]);

        return new EntitySearchResult(
            PaymentTransferRecipientEntity::ENTITY_NAME,
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * A recipient that may be paid out to - the submitter resolves it before the channel is built.
     */
    private function recipient(): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'recipient-id';
        $recipient->paymentAppId = 'app-id';
        $recipient->provider = 'alipay';
        $recipient->identityType = 'alipay_user_id';
        $recipient->identityValue = 'ciphertext';
        $recipient->identityHash = str_repeat('a', 64);
        $recipient->onboardingStatus = PaymentTransferStates::ONBOARDING_REGISTERED;
        $recipient->payoutEnabled = true;
        $recipient->setUniqueIdentifier($recipient->id);

        return $recipient;
    }
}
