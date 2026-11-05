<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\Request\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
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
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Account\_fixtures\PaymentAccountLockStub;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Symfony\Component\Clock\MockClock;

/**
 * The submission guards, channel failure audit and booking boundaries.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferSubmitter::class)]
class PaymentTransferSubmitterTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    private const NOW = '2026-10-18T09:00:00+00:00';

    /**
     * What the four interfaces declare for their payouts: they pay out and they answer what became of
     * a payout.
     */
    private const TRANSFER_CAPABILITIES = [
        PaymentCapability::CAPTURE,
        PaymentCapability::TRANSFER,
        PaymentCapability::TRANSFER_QUERY,
    ];

    /**
     * The credentials are resolved from the app, so a transfer of another app would be paid out of this
     * merchant account.
     */
    public function testATransferOfAnotherAppIsRefused(): void
    {
        try {
            $this->submitter()->submit($this->app('app-id'), $this->transfer(['paymentAppId' => 'other-app']));
            static::fail('a payout of another app has to be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_FOREIGN_APP, $exception->getErrorCode());
            // the app that tried to pay it out, not the one the transfer belongs to
            static::assertSame('app-id', $exception->getParameter('appId'));
        }
    }

    /**
     * Submitting twice is not a retry but a second payout, and the guard has to fire before the
     * credentials are resolved at all: the resolver below knows no channel, so reaching it would raise
     * `PAYMENT__UNKNOWN_CHANNEL` instead of the state conflict.
     */
    public function testATransferThatIsNotCreatedIsRefusedBeforeTheChannelIsResolved(): void
    {
        $transfer = $this->transfer([
            'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_SUBMITTED),
        ]);

        try {
            $this->submitter()->submit($this->app('app-id'), $transfer);
            static::fail('a submitted transfer must not be submitted again');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_ALREADY_FINALIZED, $exception->getErrorCode());
            static::assertSame(PaymentTransferStates::STATE_SUBMITTED, $exception->getParameter('state'));
        }
    }

    /**
     * A transfer whose state was not read can not be classified, and sending it is the one thing that
     * must never happen - the guard fails closed instead of assuming `created`.
     */
    public function testAStaleUnclaimedEntityCannotResubmitAnAlreadyClaimedPayout(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(0);
        $transfer = $this->createdTransfer();

        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));

        $this->submitter($gateway, connection: $connection)->submit($this->app('app-id'), $transfer);
    }

    public function testAnUnansweredPayoutCannotBeResubmittedAfterTheIdempotencyWindow(): void
    {
        $transfer = $this->createdTransfer();
        $transfer->submitAttempts = 1;
        $transfer->setCreatedAt(new \DateTimeImmutable('2026-10-16T08:00:00+00:00'));
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));

        $this->submitter($gateway)->submit($this->app('app-id'), $transfer);
    }

    public function testATransferWithoutAStateIsRefused(): void
    {
        try {
            $this->submitter()->submit($this->app('app-id'), $this->transfer());
            static::fail('a transfer whose state is unknown must not be submitted');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_ALREADY_FINALIZED, $exception->getErrorCode());
        }
    }

    /**
     * A domain error names its own cause and its own status. Wrapping it would report a missing
     * parameter as a gateway outage and turn a 4xx into a 5xx the caller believes it may retry.
     */
    public function testADomainErrorIsNotWrappedIntoAGatewayError(): void
    {
        $error = PaymentException::missingParameter('channel_params.transfer_scene_name');

        $attempts = [];
        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $data) use (&$attempts): EntityWrittenContainerEvent {
                $attempts[] = $data[0];

                return $this->written();
            });

        try {
            $this->submitter($this->gatewayThatThrows($error), $transfers)
                ->submit($this->app('app-id'), $this->createdTransfer());
            static::fail('the domain error has to surface');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::MISSING_PARAMETER, $exception->getErrorCode());
            static::assertSame('channel_params.transfer_scene_name', $exception->getParameter('name'));
        }

        // the attempt is still recorded: "someone tried and this is why it failed" is true even when
        // nothing was sent, and the counter carries no behaviour
        static::assertCount(1, $attempts);
        static::assertSame(1, $attempts[0]['submitAttempts']);
        static::assertStringContainsString('channel_params.transfer_scene_name', (string) $attempts[0]['failureReason']);
    }

    /**
     * An interface that never declared a payout is refused before anything is sent, and before the
     * attempt is recorded: nothing was attempted, so nothing may be written as an attempt - and the
     * caller gets a 400 naming the capability instead of a 502 on money it would retry.
     */
    public function testAnInterfaceWithoutTransferIsRefusedBeforeAnythingIsSent(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->never())->method('update');

        $transferService = $this->transferService($transfers);
        $submitter = new PaymentTransferSubmitter(
            $this->resolverFor($gateway, [PaymentCapability::CAPTURE, PaymentCapability::REFUND]),
            $transferService,
            new PaymentTransferResultBooker($transferService, $this->connection()),
        );

        $this->expectExceptionObject(PaymentProviderException::providerLacksCapability('alipay', PaymentCapability::TRANSFER->value));

        $submitter->submit($this->app('app-id'), $this->createdTransfer());
    }

    /**
     * A channel that refuses a payout names the reason in its answer, and the answer is the only place
     * that has it - the code alone (`PAYEE_NOT_EXIST`) does not say what the holder should fix.
     */
    public function testAFailureIsBookedWithTheAnswerOfTheChannel(): void
    {
        $response = [
            'code' => '40004',
            'sub_code' => 'PAYEE_NOT_EXIST',
            'sub_msg' => '收款账号不存在',
        ];

        $written = [];
        $this->submitter($this->answeringGateway($response, new TransferResult(
            TransferResult::STATE_FAILED,
            providerRef: 'order-1',
            failureCode: 'PAYEE_NOT_EXIST',
        )), $this->writingRepository($written))
            ->submit($this->app('app-id'), $this->createdTransfer());

        // the submission is the first write, the failure the second
        static::assertSame('order-1', $written[0]['providerRef']);
        static::assertSame('PAYEE_NOT_EXIST', $written[1]['failureCode']);
        static::assertSame(
            json_encode($response, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            $written[1]['failureReason']
        );
        static::assertNotNull($written[1]['failureReason']);
    }

    /**
     * `returned` carries no failure code, so the state is the code - a row that says nothing at all
     * would leave the case undiagnosable.
     */
    public function testAReturnedAnswerStillNamesACodeAndTheAnswer(): void
    {
        $written = [];
        $this->submitter($this->answeringGateway(
            ['status' => 'REFUND'],
            new TransferResult(TransferResult::STATE_RETURNED, 'order-1')
        ), $this->writingRepository($written))
            ->submit($this->app('app-id'), $this->createdTransfer());

        static::assertSame(TransferResult::STATE_RETURNED, $written[1]['failureCode']);
        static::assertStringContainsString('REFUND', (string) $written[1]['failureReason']);
    }

    /**
     * A channel answer is not bounded by us - it has to fit the column of the row that keeps it.
     */
    public function testAnOverlongAnswerIsCutToTheColumnWidth(): void
    {
        $written = [];
        $this->submitter($this->answeringGateway(
            ['sub_msg' => str_repeat('错', 400)],
            new TransferResult(TransferResult::STATE_FAILED, 'order-1', failureCode: 'PAYEE_NOT_EXIST')
        ), $this->writingRepository($written))
            ->submit($this->app('app-id'), $this->createdTransfer());

        static::assertSame(255, mb_strlen((string) $written[1]['failureReason']));
    }

    public function testAnUnknownAnswerWithoutAnObjectIsNotBooked(): void
    {
        $transfers = StaticEntityRepository::of(PaymentTransferCollection::class);

        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $connection->method('transactional')->willThrowException(new \LogicException('Unknown answers do not book transitions.'));

        $result = $this->submitter(
            $this->answeringGateway([], new TransferResult(TransferResult::STATE_UNKNOWN)),
            $transfers,
            $connection,
        )->submit($this->app('app-id'), $this->createdTransfer());

        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
        static::assertSame(1, $transfers->updates[0][0]['submitAttempts']);
    }

    /**
     * A channel that answers `processing` without naming the object it created violates its contract,
     * and booking that answer would produce a payout nobody can ever query.
     *
     * The answer itself is still the proof that the request reached the channel, so the attempt has to
     * be recorded: the counter is the only evidence the submit endpoint compares against, and without
     * it a payout that may already be on its way is reported as a rejection with a 502 - the one answer
     * that makes a caller retry a transfer that has already left.
     */
    public function testAnAnswerThatCanNotBeBookedStillCountsAsAnAttempt(): void
    {
        $written = [];

        try {
            // `processing` carries an object reference at every interface - this one names none
            $this->submitter(
                $this->answeringGateway([], new TransferResult(TransferResult::STATE_PROCESSING)),
                $this->repositoryReading($written, [$this->createdTransfer()])
            )->submit($this->app('app-id'), $this->createdTransfer());

            static::fail('an answer without an object reference must not be booked');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        // nothing was booked, so the attempt is the only write - and the row it is counted on is the
        // one that was read back, not the stale object of the caller
        static::assertCount(1, $written);
        static::assertSame(str_repeat('a', 32), $written[0]['id']);
        static::assertSame(1, $written[0]['submitAttempts']);
        static::assertStringContainsString('without an object reference', (string) $written[0]['failureReason']);
    }

    /**
     * A multi step answer can break after a step already reached the row: `markSubmitted()` counted the
     * attempt and moved the state before `markSucceeded()` failed. The attempt must not be counted a
     * second time - the counter is an audit trail and is compared by the endpoint, and the stale value
     * of the in memory row would overwrite the one the service just wrote.
     */
    public function testAnAnswerThatWasHalfwayBookedIsNotCountedTwice(): void
    {
        $written = [];

        try {
            $this->submitter(
                $this->answeringGateway([], new TransferResult(TransferResult::STATE_SUCCEEDED, 'order-1')),
                $this->repositoryReading(
                    $written,
                    // the row as `apply()` left it: submitted, and the attempt already counted
                    [$this->transfer([
                        'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_SUBMITTED),
                        'submitAttempts' => 1,
                    ])],
                    // the write of `markSucceeded()` is the one that breaks
                    2
                )
            )->submit($this->app('app-id'), $this->createdTransfer());

            static::fail('a booking that breaks halfway has to surface');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        // the submission was written, the failed step was not - and no second attempt was recorded
        static::assertCount(1, $written);
        static::assertSame(1, $written[0]['submitAttempts']);
        static::assertSame('order-1', $written[0]['providerRef']);
    }

    public function testTheChannelCallIsOutsideTheSingleResultBookingTransaction(): void
    {
        $transactionCalls = 0;
        $transactionNestingLevel = 0;
        $observations = [];

        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $connection->method('getTransactionNestingLevel')->willReturnCallback(
            static function () use (&$transactionNestingLevel): int {
                return $transactionNestingLevel;
            }
        );
        $connection->method('transactional')->willReturnCallback(
            static function (\Closure $closure) use ($connection, &$transactionCalls, &$transactionNestingLevel): mixed {
                ++$transactionCalls;
                ++$transactionNestingLevel;

                try {
                    return $closure($connection);
                } finally {
                    --$transactionNestingLevel;
                }
            }
        );

        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(
            static function (PaymentTransferRequest $request) use ($connection, &$observations): void {
                $observations[] = ['channel', $connection->getTransactionNestingLevel()];
                $model = $request->getModel();
                static::assertInstanceOf(PaymentGatewayModel::class, $model);
                $model->offsetSet(
                    PaymentGatewayModel::RESULT,
                    new TransferResult(TransferResult::STATE_SUCCEEDED, 'order-1')
                );
            }
        );

        $transfers = static::createStub(EntityRepository::class);
        $transfers->method('update')->willReturnCallback(
            function (array $data) use ($connection, &$observations): EntityWrittenContainerEvent {
                $observations[] = ['write', $connection->getTransactionNestingLevel()];

                return $this->written();
            }
        );

        $this->submitter($gateway, $transfers, $connection)
            ->submit($this->app('app-id'), $this->createdTransfer());

        static::assertSame(1, $transactionCalls);
        static::assertSame([
            ['channel', 0],
            ['write', 1],
            ['write', 1],
        ], $observations);
    }

    /**
     * @param EntityRepository<PaymentTransferCollection>|null $transfers
     */
    private function submitter(
        ?GatewayInterface $gateway = null,
        ?EntityRepository $transfers = null,
        ?Connection $connection = null,
    ): PaymentTransferSubmitter {
        $resolver = $gateway instanceof GatewayInterface
            ? $this->resolverFor($gateway)
            : $this->resolverWithoutChannels();
        $transferService = $this->transferService($transfers);

        return new PaymentTransferSubmitter(
            $resolver,
            $transferService,
            new PaymentTransferResultBooker($transferService, $connection ?? $this->connection()),
        );
    }

    private function connection(): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $connection->method('transactional')->willReturnCallback(
            static fn (\Closure $closure): mixed => $closure($connection)
        );

        return $connection;
    }

    private function gatewayThatThrows(PaymentException $error): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException($error);

        return $gateway;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function answeringGateway(array $response, TransferResult $result): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($response, $result): void {
            static::assertInstanceOf(PaymentTransferRequest::class, $request);
            // the parameters the payout was booked with are the ones it is built with - they survive
            // the row and are handed to the adapter again on every attempt
            static::assertSame(['transfer_scene_name' => '佣金报酬'], $request->channelParams);

            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);

            $model->offsetSet(PaymentGatewayModel::RESPONSE, $response);
            $model->offsetSet(PaymentGatewayModel::RESULT, $result);
        });

        return $gateway;
    }

    /**
     * @param list<array<string, mixed>> $written
     *
     * @return EntityRepository<PaymentTransferCollection>
     */
    private function writingRepository(array &$written): EntityRepository
    {
        $transfers = static::createStub(EntityRepository::class);
        $transfers->method('update')->willReturnCallback(function (array $data) use (&$written): EntityWrittenContainerEvent {
            $written[] = $data[0];

            return $this->written();
        });

        return $transfers;
    }

    /**
     * A repository that answers the re-read of the row and records every write.
     *
     * `$fresh` is what `getById()` has to answer, one row per read, which is how the state of the row
     * after the answer of the channel is reproduced. `$failAtWrite` makes one write break before it
     * reaches the store - the way a booking that fails halfway is modelled.
     *
     * @param list<array<string, mixed>> $written
     * @param list<PaymentTransferEntity> $fresh
     *
     * @return EntityRepository<PaymentTransferCollection>
     */
    private function repositoryReading(array &$written, array $fresh = [], ?int $failAtWrite = null): EntityRepository
    {
        $transfers = static::createStub(EntityRepository::class);

        $transfers->method('update')->willReturnCallback(function (array $data) use (&$written, $failAtWrite): EntityWrittenContainerEvent {
            if ($failAtWrite !== null && \count($written) + 1 === $failAtWrite) {
                throw new \RuntimeException('the row could not be written');
            }

            $written[] = $data[0];

            return $this->written();
        });

        $transfers->method('search')->willReturnCallback(function () use (&$fresh): EntitySearchResult {
            $entity = array_shift($fresh);

            $collection = new PaymentTransferCollection($entity instanceof PaymentTransferEntity ? [$entity] : []);

            return new EntitySearchResult(
                PaymentTransferEntity::ENTITY_NAME,
                $collection->count(),
                $collection,
                null,
                new Criteria(),
                Context::createDefaultContext()
            );
        });

        return $transfers;
    }

    private function written(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
    }

    /**
     * A resolver that knows no interface and no channel: any attempt to resolve one fails loudly, which
     * is exactly what proves a guard ran before the channel was reached.
     */
    private function resolverWithoutChannels(): PaymentConfigResolver
    {
        $providers = new PaymentProviderRegistry([]);

        return new PaymentConfigResolver(
            $providers,
            new PaymentProductRegistry($providers),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher(self::SECRET),
            static::createStub(EntityRepository::class),
        );
    }

    /**
     * A resolver that actually resolves: one app, authorized for alipay, with the channel enabled - and
     * the given gateway handed out by the factory of the interface.
     *
     * @param list<PaymentCapability> $capabilities
     */
    private function resolverFor(GatewayInterface $gateway, array $capabilities = self::TRANSFER_CAPABILITIES): PaymentConfigResolver
    {
        $cipher = new PaymentCredentialCipher(self::SECRET);

        $channel = new PaymentProduct(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            method: 'alipay',
            label: 'Alipay H5',
            interactions: [PaymentInteraction::REDIRECT],
        );

        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            products: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: $capabilities,
        );

        return PaymentTransferRulesFixture::boundResolver($provider, $cipher);
    }

    /**
     * @param EntityRepository<PaymentTransferCollection>|null $transfers
     */
    private function transferService(?EntityRepository $transfers = null): PaymentTransferService
    {
        $transfers ??= static::createStub(EntityRepository::class);

        $recipients = static::createStub(EntityRepository::class);
        $recipients->method('search')->willReturn(new EntitySearchResult(
            PaymentTransferRecipientEntity::ENTITY_NAME,
            1,
            new PaymentTransferRecipientCollection([$this->recipient()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $registry = static::createStub(StateMachineRegistry::class);
        $registry->method('transition')->willReturn(new StateMachineStateCollection());

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $cipher = new PaymentCredentialCipher(self::SECRET);

        return new PaymentTransferService(
            $transfers,
            $recipients,
            new PaymentTransferStateHandler($registry),
            new PaymentTransferRecipientService($recipients, $cipher, new MockClock(self::NOW), PaymentTransferRulesFixture::registry()),
            $cipher,
            $initialStateIdLoader,
            new MockClock(self::NOW),
            PaymentTransferRulesFixture::registry(),
            NumberRangeFixture::generator('PT1001'),
            PaymentTransferRulesFixture::resolvedConfig(),
            new PaymentAccountLockStub(),
        );
    }

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

    /**
     * A transfer that may be submitted: `created`, with the channel parameters it was booked with.
     */
    private function createdTransfer(): PaymentTransferEntity
    {
        return $this->transfer([
            'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_CREATED),
            'channelParams' => ['transfer_scene_name' => '佣金报酬'],
        ]);
    }

    private function app(string $id): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = $id;
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;

        return $app;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function transfer(array $overrides = []): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->id = str_repeat('a', 32);
        $transfer->paymentAppId = 'app-id';
        $transfer->accountId = 'account-id';
        $transfer->transferNo = 'PT261018090000ABCDEF0123';
        $transfer->outNo = 'commission0001';
        $transfer->recipientId = 'recipient-id';
        $transfer->provider = 'alipay';
        $transfer->channel = 'alipay_h5';
        $transfer->amount = 100;
        $transfer->currency = 'CNY';
        $transfer->statusId = str_repeat('b', 32);
        $transfer->submitAttempts = 0;
        $transfer->assign($overrides);

        $transfer->setUniqueIdentifier($transfer->id);

        return $transfer;
    }

    private function stateOf(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier($technicalName);
        $state->setTechnicalName($technicalName);

        return $state;
    }
}
