<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferSubmitter;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\Clock\MockClock;

/**
 * What a query answer means for the state of the transfer, depending on where the transfer already is.
 *
 * The path is state-aware because the same answer means a different transition from a different
 * origin - and because a step the current state does not allow would be an illegal transition instead
 * of a correction.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferReconciler::class)]
class PaymentTransferReconcilerTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    private const NOW = '2026-10-18T09:00:00+00:00';

    /**
     * A transfer that was only submitted has to reach `processing` by a real transition first: the
     * machine has no `submitted -> succeeded` edge to lean on.
     */
    public function testASucceededAnswerFromSubmittedProcessesFirst(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_PROCESS, PaymentTransferSubmitter::STEP_SUCCEED],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_SUCCEEDED),
                PaymentTransferStates::STATE_SUBMITTED
            )
        );
    }

    /**
     * Wechat answers `SUCCESS` for a transfer the shopper has accepted - `process` exists from
     * `awaiting_user_confirm` as well, so the same two steps are the legal path.
     */
    public function testASucceededAnswerFromAwaitingConfirmProcessesFirst(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_PROCESS, PaymentTransferSubmitter::STEP_SUCCEED],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_SUCCEEDED),
                PaymentTransferStates::STATE_AWAITING_USER_CONFIRM
            )
        );
    }

    /**
     * Already observed as processing - `process` would be an illegal transition.
     */
    public function testASucceededAnswerFromProcessingOnlySettles(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_SUCCEED],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_SUCCEEDED),
                PaymentTransferStates::STATE_PROCESSING
            )
        );
    }

    /**
     * A settled transfer has nothing left to succeed - and `succeed` from `succeeded` is not an edge.
     */
    public function testASucceededAnswerForASettledTransferChangesNothing(): void
    {
        static::assertSame(
            [],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_SUCCEEDED),
                PaymentTransferStates::STATE_SUCCEEDED
            )
        );
    }

    #[DataProvider('movingStates')]
    public function testAMovingAnswerProcessesASubmittedTransfer(string $state): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_PROCESS],
            PaymentTransferReconciler::stepNames($this->answer($state), PaymentTransferStates::STATE_SUBMITTED)
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function movingStates(): iterable
    {
        yield 'processing' => [TransferResult::STATE_PROCESSING];
        yield 'accepted' => [TransferResult::STATE_ACCEPTED];
    }

    public function testAnAwaitingConfirmAnswerMovesASubmittedTransferToTheConfirmation(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_AWAIT_CONFIRM],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_AWAITING_CONFIRM),
                PaymentTransferStates::STATE_SUBMITTED
            )
        );
    }

    /**
     * The transfer waits for the shopper already - asking it to wait again is not a transition.
     */
    public function testAnAwaitingConfirmAnswerForAWaitingTransferChangesNothing(): void
    {
        static::assertSame(
            [],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_AWAITING_CONFIRM),
                PaymentTransferStates::STATE_AWAITING_USER_CONFIRM
            )
        );
    }

    /**
     * `fail` is legal from every state the reconciler asks about - including `succeeded`, where stripe
     * arrives within five business days.
     */
    #[DataProvider('openStates')]
    public function testAFailedAnswerFailsEveryOpenState(string $currentState): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_FAIL],
            PaymentTransferReconciler::stepNames($this->answer(TransferResult::STATE_FAILED), $currentState)
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function openStates(): iterable
    {
        yield 'submitted' => [PaymentTransferStates::STATE_SUBMITTED];
        yield 'awaiting confirmation' => [PaymentTransferStates::STATE_AWAITING_USER_CONFIRM];
        yield 'processing' => [PaymentTransferStates::STATE_PROCESSING];
        yield 'succeeded' => [PaymentTransferStates::STATE_SUCCEEDED];
    }

    /**
     * Alipay refunds a card transfer and paypal returns a claimed payout - both are observed after the
     * money arrived.
     */
    public function testAReturnedAnswerMarksASettledTransfer(): void
    {
        static::assertSame(
            [PaymentTransferReconciler::STEP_MARK_RETURNED],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_RETURNED),
                PaymentTransferStates::STATE_SUCCEEDED
            )
        );
    }

    /**
     * Paypal's `UNCLAIMED -> RETURNED` never passes through `SUCCESS`: the item was never claimed, so
     * the transfer is `processing` when it comes back and moves straight on.
     */
    public function testAReturnedAnswerMarksAProcessingTransfer(): void
    {
        static::assertSame(
            [PaymentTransferReconciler::STEP_MARK_RETURNED],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_RETURNED),
                PaymentTransferStates::STATE_PROCESSING
            )
        );
    }

    /**
     * There is no `submitted -> returned` edge, so the transfer reaches `processing` first - the
     * return of a payout that never arrived is only expressible that way.
     */
    public function testAReturnedAnswerProcessesASubmittedTransferFirst(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_PROCESS, PaymentTransferReconciler::STEP_MARK_RETURNED],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_RETURNED),
                PaymentTransferStates::STATE_SUBMITTED
            )
        );
    }

    public function testAReturnedAnswerProcessesAWaitingTransferFirst(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_PROCESS, PaymentTransferReconciler::STEP_MARK_RETURNED],
            PaymentTransferReconciler::stepNames(
                $this->answer(TransferResult::STATE_RETURNED),
                PaymentTransferStates::STATE_AWAITING_USER_CONFIRM
            )
        );
    }

    /**
     * A channel that answers nothing must never move a transfer.
     */
    #[DataProvider('openStates')]
    public function testAnUnknownAnswerChangesNothing(string $currentState): void
    {
        static::assertSame([], PaymentTransferReconciler::stepNames($this->answer(TransferResult::STATE_UNKNOWN), $currentState));
    }

    /**
     * The credentials are resolved from the app - asking about a transfer of another app would move it
     * on this merchant account.
     */
    public function testATransferOfAnotherAppIsRefused(): void
    {
        try {
            $this->reconciler()->reconcile($this->app('app-id'), $this->transfer([
                'paymentAppId' => 'other-app',
                'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_SUBMITTED),
                'providerRef' => 'order-1',
            ]));
            static::fail('a payout of another app has to be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_FOREIGN_APP, $exception->getErrorCode());
        }
    }

    /**
     * A `created` transfer was never handed to the interface and a terminal one has nothing left to
     * resolve. The resolver below knows no channel, so reaching it would raise
     * `PAYMENT__UNKNOWN_CHANNEL` - the answer `unknown` proves the channel was not asked at all.
     */
    #[DataProvider('closedStates')]
    public function testATransferThatIsNotOnItsWayIsNotAskedAbout(string $currentState): void
    {
        $result = $this->reconciler()->reconcile($this->app('app-id'), $this->transfer([
            'statusMachineState' => $this->stateOf($currentState),
            'providerRef' => 'order-1',
        ]));

        static::assertSame(TransferResult::STATE_UNKNOWN, $result->state);
        static::assertSame('', $result->providerStatus);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function closedStates(): iterable
    {
        yield 'created' => [PaymentTransferStates::STATE_CREATED];
        yield 'failed' => [PaymentTransferStates::STATE_FAILED];
        yield 'canceled' => [PaymentTransferStates::STATE_CANCELED];
        yield 'returned' => [PaymentTransferStates::STATE_RETURNED];
    }

    /**
     * An open transfer always names the object the submission created. A row without one is a defect
     * of the submission and is reported as such - not as a missing field of the recipient, which would
     * send whoever reads the log in the wrong direction.
     */
    public function testAnOpenTransferWithoutAProviderRefIsReportedAsADefect(): void
    {
        try {
            $this->reconciler()->reconcile($this->app('app-id'), $this->transfer([
                'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_SUBMITTED),
            ]));
            static::fail('an open transfer without a provider reference has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_PROVIDER_REF_MISSING, $exception->getErrorCode());
            static::assertSame('commission0001', $exception->getParameter('outNo'));
        }
    }

    /**
     * The window decides which payouts are never asked about again: too narrow and a real payout is
     * forgotten, too wide and a stuck one occupies a slot of every batch forever.
     */
    public function testTheBatchReachesBackThirtyDays(): void
    {
        $values = PaymentTransferReconciler::dueCriteriaValues(new \DateTimeImmutable(self::NOW));

        static::assertSame(
            [
                PaymentTransferStates::STATE_SUBMITTED,
                PaymentTransferStates::STATE_PROCESSING,
                PaymentTransferStates::STATE_AWAITING_USER_CONFIRM,
                PaymentTransferStates::STATE_SUCCEEDED,
            ],
            $values['states']
        );
        // thirty days before the clock, in the timezone of the clock
        static::assertSame('2026-09-18T09:00:00+00:00', $values['createdAtFrom']->format(\DateTimeInterface::ATOM));
    }

    /**
     * The batch is the only thing that asks a payout nobody reported on, so what it selects - and what
     * it deliberately does not select - is the whole safety net.
     */
    public function testTheBatchSelectsOnlyThePayoutsTheChannelMayStillMove(): void
    {
        $transferRepository = static::createMock(EntityRepository::class);
        $transferRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static function (Criteria $criteria): bool {
                $filters = $criteria->getFilters();

                static::assertCount(2, $filters);

                static::assertInstanceOf(EqualsAnyFilter::class, $filters[0]);
                static::assertSame('statusId', $filters[0]->getField());
                // `succeeded` is in here on purpose - stripe and alipay still turn it into a failure
                static::assertSame(
                    ['state-submitted', 'state-processing', 'state-awaiting_user_confirm', 'state-succeeded'],
                    $filters[0]->getValue()
                );

                // a payout that has been open forever must not occupy a slot of every batch
                static::assertInstanceOf(RangeFilter::class, $filters[1]);
                static::assertSame('createdAt', $filters[1]->getField());
                static::assertSame(
                    [RangeFilter::GTE => '2026-09-18 09:00:00.000'],
                    $filters[1]->getParameters()
                );

                // all three are read by reconcile(): without the state every row would look closed,
                // without the app the credentials cannot be resolved, without the recipient alipay
                // cannot be asked at all
                static::assertSame(
                    ['statusMachineState', 'paymentApp', 'recipient'],
                    array_keys($criteria->getAssociations())
                );

                // oldest first - a payout that has been waiting longest is not pushed out of every
                // batch by younger ones
                $sorting = array_values($criteria->getSorting());
                static::assertCount(1, $sorting);
                static::assertSame('createdAt', $sorting[0]->getField());
                static::assertSame(FieldSorting::ASCENDING, $sorting[0]->getDirection());

                static::assertSame(25, $criteria->getLimit());

                return true;
            }))
            ->willReturn($this->transferResult());

        $reconciler = $this->reconciler($transferRepository, $this->stateRepository());

        static::assertSame(0, $reconciler->reconcileDue(25));
    }

    /**
     * A payout the channel cannot be asked about must not keep the others from being asked - the next
     * run tries it again. Only the answered one counts, so the caller can tell a quiet batch from a
     * working one.
     */
    public function testOnePayoutTheChannelCannotBeAskedAboutDoesNotStopTheBatch(): void
    {
        // the channel of the first transfer is known to the resolver, the one of the second is not
        $answered = $this->transfer([
            'providerRef' => 'order-1',
            'paymentApp' => $this->app('app-id'),
            'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_SUBMITTED),
        ]);
        $broken = $this->transfer([
            'id' => 'transfer-id-2',
            'outNo' => 'commission0002',
            'providerRef' => 'order-2',
            'channel' => 'wechat_transfer',
            'paymentApp' => $this->app('app-id'),
            'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_SUBMITTED),
        ]);

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())
            ->method('execute')
            ->with(static::isInstanceOf(PaymentTransferQuery::class))
            ->willReturnCallback(static function (PaymentTransferQuery $query): void {
                $model = $query->getModel();
                static::assertInstanceOf(PaymentGatewayModel::class, $model);

                $model->offsetSet(
                    PaymentGatewayModel::RESULT,
                    new TransferQueryResult(TransferResult::STATE_PROCESSING, 'PROCESSING')
                );
            });

        $registry = static::createMock(StateMachineRegistry::class);
        $registry->expects($this->once())
            ->method('transition')
            ->with(static::callback(
                static fn (Transition $transition): bool => $transition->getTransitionName() === PaymentTransferStates::ACTION_PROCESS
            ))
            ->willReturn(new StateMachineStateCollection());

        $transferRepository = static::createStub(EntityRepository::class);
        $transferRepository->method('search')->willReturn($this->transferResult($answered, $broken));

        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Reconciling the payout with the channel failed.',
                static::callback(static function (array $context): bool {
                    // the out number names the payout support has to look at, the channel says where
                    static::assertSame('commission0002', $context['outNo']);
                    static::assertSame('alipay', $context['provider']);
                    static::assertSame('wechat_transfer', $context['channel']);
                    static::assertArrayHasKey('error', $context);

                    return true;
                })
            );

        $reconciler = $this->reconciler(
            $transferRepository,
            $this->stateRepository(),
            $this->resolverFor($gateway),
            $this->transferService($this->recipientRepository(), $registry),
            $logger
        );

        static::assertSame(1, $reconciler->reconcileDue());
    }

    /**
     * The foreign key to the app is required - an app that did not load is a defect of the query, and
     * one the batch has to report instead of hiding. Asking anyway would resolve credentials of
     * nothing.
     */
    public function testAPayoutWhoseAppWasNotLoadedIsReportedInsteadOfAsked(): void
    {
        $orphan = $this->transfer(['providerRef' => 'order-1']);

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $transferRepository = static::createStub(EntityRepository::class);
        $transferRepository->method('search')->willReturn($this->transferResult($orphan));

        // exactly one warning: the row is reported as not loaded, not as a failed query
        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'The payout cannot be asked about, its app was not loaded.',
                static::callback(static fn (array $context): bool => $context['outNo'] === 'commission0001')
            );

        $reconciler = $this->reconciler(
            $transferRepository,
            $this->stateRepository(),
            $this->resolverFor($gateway),
            $this->transferService($this->recipientRepository()),
            $logger
        );

        static::assertSame(0, $reconciler->reconcileDue());
    }

    /**
     * A state machine without one of the open states would silently never select its payouts - they
     * would wait forever without a single log line. A broken installation has to say so instead.
     */
    public function testAStateMachineMissingAnOpenStateFailsTheBatchInsteadOfSkippingItsPayouts(): void
    {
        $transferRepository = static::createMock(EntityRepository::class);
        $transferRepository->expects($this->never())->method('search');

        $reconciler = $this->reconciler(
            $transferRepository,
            $this->stateRepository(
                PaymentTransferStates::STATE_SUBMITTED,
                PaymentTransferStates::STATE_PROCESSING,
                PaymentTransferStates::STATE_AWAITING_USER_CONFIRM
            )
        );

        try {
            $reconciler->reconcileDue();
            static::fail('a state machine that does not declare every open state has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::STATE_MACHINE_INVALID, $exception->getErrorCode());
            static::assertSame(PaymentTransferStates::STATE_MACHINE, $exception->getParameter('stateMachine'));
        }
    }

    /**
     * @param EntityRepository<PaymentTransferCollection>|null $transferRepository
     * @param EntityRepository<StateMachineStateCollection>|null $stateRepository
     */
    private function reconciler(
        ?EntityRepository $transferRepository = null,
        ?EntityRepository $stateRepository = null,
        ?PaymentConfigResolver $configResolver = null,
        ?PaymentTransferService $transferService = null,
        ?LoggerInterface $logger = null,
    ): PaymentTransferReconciler {
        return new PaymentTransferReconciler(
            $configResolver ?? $this->configResolver(),
            $transferService ?? $this->transferService(),
            $transferRepository ?? static::createStub(EntityRepository::class),
            $stateRepository ?? $this->stateRepository(),
            new MockClock(self::NOW),
            $logger ?? static::createStub(LoggerInterface::class),
        );
    }

    /**
     * @return EntityRepository<StateMachineStateCollection>
     */
    private function stateRepository(string ...$states): EntityRepository
    {
        $states = $states === [] ? [
            PaymentTransferStates::STATE_SUBMITTED,
            PaymentTransferStates::STATE_PROCESSING,
            PaymentTransferStates::STATE_AWAITING_USER_CONFIRM,
            PaymentTransferStates::STATE_SUCCEEDED,
        ] : $states;

        $rows = [];
        foreach ($states as $state) {
            $entity = new StateMachineStateEntity();
            $entity->setId('state-' . $state);
            $entity->setTechnicalName($state);
            $rows[] = $entity;
        }

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'state_machine_state',
            \count($rows),
            new StateMachineStateCollection($rows),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    /**
     * @return EntityRepository<PaymentTransferRecipientCollection>
     */
    private function recipientRepository(): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            PaymentTransferRecipientEntity::ENTITY_NAME,
            1,
            new PaymentTransferRecipientCollection([$this->recipient()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    /**
     * A resolver that actually resolves: one app, authorized for alipay, with the channel enabled - and
     * the given gateway handed out by the factory of the interface.
     */
    private function resolverFor(GatewayInterface $gateway): PaymentConfigResolver
    {
        $cipher = new PaymentCredentialCipher(self::SECRET);

        $channel = new PaymentChannel(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            label: 'Alipay H5',
            flow: PaymentFlow::REDIRECT,
        );

        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $providers = new PaymentProviderRegistry([$provider]);

        $appProvider = new PaymentAppProviderEntity();
        $appProvider->setUniqueIdentifier('app-provider-id');
        $appProvider->paymentAppId = 'app-id';
        $appProvider->providerTechnicalName = 'alipay';
        $appProvider->enabled = true;
        $appProvider->mode = PaymentAppProviderEntity::MODE_OWN;
        $appProvider->credentials = $cipher->encrypt(['app_id' => 'own-app-id']);

        $appChannel = new PaymentAppChannelEntity();
        $appChannel->setUniqueIdentifier('app-channel-id');
        $appChannel->paymentAppId = 'app-id';
        $appChannel->channelTechnicalName = 'alipay_h5';
        $appChannel->enabled = true;
        $appChannel->params = $cipher->encrypt(['appid' => 'own-appid']);

        $channelConfig = new PaymentChannelConfigEntity();
        $channelConfig->setUniqueIdentifier('channel-config-id');
        $channelConfig->providerTechnicalName = 'alipay';
        $channelConfig->channelTechnicalName = 'alipay_h5';
        $channelConfig->enabled = true;
        $channelConfig->params = $cipher->encrypt(['appid' => 'platform-appid']);

        $appProviderRepository = static::createStub(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn($this->singleEntityResult('payment_app_provider', $appProvider));

        $appChannelRepository = static::createStub(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn($this->singleEntityResult('payment_app_channel', $appChannel));

        $channelConfigRepository = static::createStub(EntityRepository::class);
        $channelConfigRepository->method('search')->willReturn($this->singleEntityResult('payment_channel_config', $channelConfig));

        return new PaymentConfigResolver(
            $providers,
            new PaymentChannelRegistry($providers),
            $appProviderRepository,
            static::createStub(EntityRepository::class),
            $appChannelRepository,
            $channelConfigRepository,
            $cipher,
        );
    }

    /**
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private function singleEntityResult(string $entityName, Entity $entity): EntitySearchResult
    {
        return new EntitySearchResult(
            $entityName,
            1,
            new EntityCollection([$entity]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<PaymentTransferCollection>
     */
    private function transferResult(PaymentTransferEntity ...$transfers): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentTransferEntity::ENTITY_NAME,
            \count($transfers),
            new PaymentTransferCollection($transfers),
            null,
            new Criteria(),
            Context::createDefaultContext()
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
     * A resolver that knows no interface and no channel: any attempt to resolve one fails loudly, which
     * is what proves a guard ran before the channel was reached.
     */
    private function configResolver(): PaymentConfigResolver
    {
        $providers = new PaymentProviderRegistry([]);

        return new PaymentConfigResolver(
            $providers,
            new PaymentChannelRegistry($providers),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher(self::SECRET),
        );
    }

    /**
     * @param EntityRepository<PaymentTransferRecipientCollection>|null $recipients
     */
    private function transferService(?EntityRepository $recipients = null, ?StateMachineRegistry $registry = null): PaymentTransferService
    {
        $transfers = static::createStub(EntityRepository::class);
        $recipients ??= static::createStub(EntityRepository::class);

        $registry ??= static::createStub(StateMachineRegistry::class);
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $cipher = new PaymentCredentialCipher(self::SECRET);

        return new PaymentTransferService(
            $transfers,
            $recipients,
            new PaymentTransferStateHandler($registry),
            new PaymentTransferRecipientService($recipients, $cipher, new MockClock(self::NOW)),
            $cipher,
            $initialStateIdLoader,
            new MockClock(self::NOW),
        );
    }

    private function answer(string $state): TransferQueryResult
    {
        return new TransferQueryResult($state, strtoupper($state));
    }

    private function app(string $id): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = $id;

        return $app;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function transfer(array $overrides = []): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->id = 'transfer-id';
        $transfer->paymentAppId = 'app-id';
        $transfer->transferNo = 'PT261018090000ABCDEF0123';
        $transfer->outNo = 'commission0001';
        $transfer->recipientId = 'recipient-id';
        $transfer->provider = 'alipay';
        $transfer->channel = 'alipay_h5';
        $transfer->amount = 100;
        $transfer->currency = 'CNY';
        $transfer->statusId = 'status-id';
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
