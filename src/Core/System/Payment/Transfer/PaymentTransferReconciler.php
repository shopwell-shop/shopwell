<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * Asks the channel what became of a payout the platform is waiting for - the query side of the
 * submission, the counterpart of the reconciliation of a payment order.
 *
 * A submission may answer nothing at all (alipay's `SYSTEM_ERROR`), a notification can be lost, and
 * some interfaces only report the outcome of a payout hours later. The question is always addressed to
 * the object the submission recorded: the out number is the idempotency key, but only the reference of
 * the channel names the payout the answer belongs to.
 *
 * Querying is idempotent - it moves nothing - which is why it is the first thing a retry does
 * ("query before sending"): a second submission is only justified when the query says the first one
 * did not happen.
 *
 * Only a transfer that is on its way is asked about. A `created` transfer was never handed over and a
 * terminal one has nothing left to resolve; asking anyway would only produce answers the machine can
 * not book.
 *
 * The batch entry {@see reconcileDue()} lives here and not in the scheduled task on purpose: finding
 * the payouts that are due is domain knowledge, and a task that owned it could only be exercised
 * through messenger. Any caller - a task, a command, a replay after an outage - asks the same way.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferReconciler
{
    final public const STEP_MARK_RETURNED = 'mark_returned';

    /**
     * How far back a batch looks.
     *
     * No channel can change the outcome of a payout after thirty days: paypal closes the unclaimed
     * return window after 30 days, stripe reports a payout as failed within five business days,
     * alipay's bank return window has passed long before, and wechat's confirmation window is 24
     * hours. The bound is a liveness guard, not a performance tuning knob: without it a payout that
     * is stuck in an open state forever occupies a slot of every batch, and younger payouts - the
     * ones still worth asking about - would eventually never be reached.
     */
    private const MAX_AGE_DAYS = 30;

    /**
     * The states in which the channel may still change something. `succeeded` is one of them on
     * purpose: stripe turns a `paid` payout into `failed` within five business days, alipay refunds a
     * transfer to a card the bank refused, and paypal turns a claimed payout into `returned`.
     */
    private const OPEN_STATES = [
        PaymentTransferStates::STATE_SUBMITTED,
        PaymentTransferStates::STATE_PROCESSING,
        PaymentTransferStates::STATE_AWAITING_USER_CONFIRM,
        PaymentTransferStates::STATE_SUCCEEDED,
    ];

    /**
     * @param EntityRepository<PaymentTransferCollection> $transferRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     */
    public function __construct(
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentTransferService $transferService,
        private readonly EntityRepository $transferRepository,
        private readonly EntityRepository $stateRepository,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Asks the channel about every payout that is due and books the answers.
     *
     * @throws PaymentException
     *
     * @return int the number of payouts that were asked about
     */
    public function reconcileDue(int $limit = 100): int
    {
        $due = self::dueCriteriaValues($this->clock->now());

        $criteria = new Criteria();
        // only the payouts the channel may still move, and only those young enough for any channel to
        // still move them - see MAX_AGE_DAYS
        $criteria->addFilter(new EqualsAnyFilter('statusId', $this->openStateIds($due['states'])));
        // a date range filter compares strings, so the clock is formatted the way the storage does
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::GTE => $due['createdAtFrom']->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));
        // reconcile() reads the state name, the app and the recipient. Without the state the transfer
        // is null and every payout would be skipped as "not open" - silently, which is why the
        // association is not optional
        $criteria->addAssociation('statusMachineState');
        $criteria->addAssociation('paymentApp');
        $criteria->addAssociation('recipient');
        // oldest first: a payout that has been waiting longest must not be pushed out of every batch
        // by younger ones. The age bound keeps the queue finite, so this cannot starve the young
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);

        $transfers = $this->transferRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $asked = 0;
        foreach ($transfers as $transfer) {
            if (!$transfer instanceof PaymentTransferEntity) {
                continue;
            }

            $app = $transfer->paymentApp;

            if (!$app instanceof PaymentAppEntity) {
                // the foreign key is required, so an app that did not load is a defect of the query -
                // and a defect the batch has to report instead of hiding, while the other payouts are
                // still asked about
                $this->logger->warning('The payout cannot be asked about, its app was not loaded.', [
                    'outNo' => $transfer->outNo,
                    'provider' => $transfer->provider,
                    'channel' => $transfer->channel,
                ]);

                continue;
            }

            try {
                $this->reconcile($app, $transfer);
            } catch (\Throwable $exception) {
                // one payout the channel cannot be asked about must not keep the others from being
                // asked - the next run tries this one again
                $this->logger->warning('Reconciling the payout with the channel failed.', [
                    'outNo' => $transfer->outNo,
                    'provider' => $transfer->provider,
                    'channel' => $transfer->channel,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            ++$asked;
        }

        return $asked;
    }

    /**
     * Which payouts a batch asks about: the states in which the channel may still change the outcome,
     * and how far back the batch reaches.
     *
     * Pure on purpose: the window decides which payouts are never asked about again, and it has to be
     * assertable without a database. Too narrow and real payouts are forgotten, too wide and a stuck
     * one is asked forever.
     *
     * @return array{states: list<string>, createdAtFrom: \DateTimeImmutable}
     */
    public static function dueCriteriaValues(\DateTimeImmutable $now): array
    {
        return [
            'states' => self::OPEN_STATES,
            'createdAtFrom' => $now->modify(\sprintf('-%d days', self::MAX_AGE_DAYS)),
        ];
    }

    /**
     * Asks the channel about one payout and books its answer.
     *
     * A transfer that is not open is answered with `unknown` without asking anything: the channel was
     * not called, and saying so is the only honest answer a caller can book.
     *
     * @throws PaymentException
     */
    public function reconcile(PaymentAppEntity $app, PaymentTransferEntity $transfer): TransferQueryResult
    {
        // the credentials are resolved from the app - a transfer of another app would be asked about,
        // and possibly moved, on the merchant account of this one
        if ($transfer->paymentAppId !== $app->id) {
            throw PaymentException::transferForeignApp($app->id, $transfer->outNo);
        }

        $state = $transfer->statusMachineState?->getTechnicalName();

        if ($state === null || !\in_array($state, self::OPEN_STATES, true)) {
            return new TransferQueryResult(TransferResult::STATE_UNKNOWN, '');
        }

        // every answer is matched to the transfer by the reference of the channel; a transfer in an
        // open state without one is a defect of the submission, and asking by the out number alone
        // would be a different question than the one the channel answered
        if ($transfer->providerRef === null || $transfer->providerRef === '') {
            throw PaymentException::transferProviderRefMissing($transfer->outNo);
        }

        $recipient = $this->transferService->recipientOf($app, $transfer);

        // a payout has no device - null is the "no restriction" of the channel lookup, exactly like
        // the submission resolves it
        $resolved = $this->configResolver->resolve(
            $app,
            $transfer->channel,
            $transfer->currency,
            null,
            $transfer->channelParams ?? []
        );

        $model = PaymentGatewayModel::forTransfer($transfer);

        // An interface that never declared a payout query cannot be asked what became of a payout.
        // Without the guard payum fails inside the gateway with "no action supports this request",
        // which reads as a broken channel (502) instead of "this interface cannot be reconciled" - and
        // a payout that is never asked about stays open forever. The guard reads the resolved provider,
        // so a plugin that adds the query action makes the reconciliation reachable for that interface.
        if (!$resolved->provider->supports(PaymentCapability::TRANSFER_QUERY)) {
            throw PaymentException::providerLacksCapability($resolved->provider->technicalName, PaymentCapability::TRANSFER_QUERY->value);
        }

        // no transfer action reads the notification or the return url - only capture and refund do.
        // They are empty on purpose, like in the submission
        $gateway = $resolved->provider->gatewayFactory->createForPayment($resolved, '', '');

        $gateway->execute(new PaymentTransferQuery($model, $transfer, $recipient));
        $result = $model->result(TransferQueryResult::class, $transfer->channel);

        $this->apply($transfer, $result, $state);

        return $result;
    }

    /**
     * The service calls the answer requires, in the order the state machine allows them.
     *
     * Pure on purpose: {@see PaymentTransferService} is final and can not be doubled, so the
     * money-critical knowledge has to be assertable without a database and without a mock.
     *
     * The path depends on the state the transfer is in, because the same answer means different
     * transitions from different origins - and because a step the current state does not allow would
     * be an illegal transition instead of a correction:
     *
     *  - `returned` is the paypal case `UNCLAIMED -> RETURNED`, which never passes through a success,
     *    so a transfer that is already `processing` moves straight to `returned`,
     *  - a `returned` answer for a transfer that is only `submitted` has to reach `processing` by a
     *    real transition first, the machine has no `submitted -> returned` edge,
     *  - `process` is not limited to `submitted`: `awaiting_user_confirm -> processing` is an edge as
     *    well, and it is the same fact (the channel took the payout), so a waiting transfer answers
     *    `processing`, `accepted` and `returned` exactly like a submitted one - it is a second origin
     *    of the same step and not a copy-paste slip,
     *  - `succeeded` is only a step when the transfer is not settled yet, and `process` only when it
     *    has not been observed as processing already.
     *
     * @return list<string>
     */
    public static function stepNames(TransferQueryResult $result, string $currentState): array
    {
        $process = self::processStep($currentState);

        return match ($result->state) {
            TransferResult::STATE_SUCCEEDED => $currentState === PaymentTransferStates::STATE_SUCCEEDED
                ? []
                : [...$process, PaymentTransferSubmitter::STEP_SUCCEED],
            TransferResult::STATE_PROCESSING, TransferResult::STATE_ACCEPTED => $process,
            TransferResult::STATE_AWAITING_CONFIRM => $currentState === PaymentTransferStates::STATE_SUBMITTED
                ? [PaymentTransferSubmitter::STEP_AWAIT_CONFIRM]
                : [],
            TransferResult::STATE_FAILED => [PaymentTransferSubmitter::STEP_FAIL],
            TransferResult::STATE_RETURNED => [...$process, self::STEP_MARK_RETURNED],
            // a channel that answers nothing must not move a transfer
            default => [],
        };
    }

    /**
     * The ids of the states a batch asks about.
     *
     * A state machine that is missing one of them cannot be reconciled against safely: the missing
     * state would simply never be selected, and its payouts would wait forever without a single log
     * line - a broken installation has to say so instead.
     *
     * @param list<string> $states
     *
     * @throws PaymentException
     *
     * @return list<string>
     */
    private function openStateIds(array $states): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('stateMachine.technicalName', PaymentTransferStates::STATE_MACHINE),
            new EqualsAnyFilter('technicalName', $states)
        );

        $ids = [];
        foreach ($this->stateRepository->search($criteria, Context::createDefaultContext())->getEntities() as $state) {
            if ($state instanceof StateMachineStateEntity) {
                $ids[] = $state->getId();
            }
        }

        if (\count($ids) !== \count($states)) {
            throw PaymentException::stateMachineInvalid(
                PaymentTransferStates::STATE_MACHINE,
                \sprintf('it declares only %d of the %d states a payout is reconciled in', \count($ids), \count($states))
            );
        }

        return $ids;
    }

    /**
     * The `process` step for the current state, or nothing when the transfer is there already or can
     * not reach it: `process` exists from `submitted` and `awaiting_user_confirm` only.
     *
     * @return list<string>
     */
    private static function processStep(string $currentState): array
    {
        return match ($currentState) {
            PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM => [PaymentTransferSubmitter::STEP_PROCESS],
            default => [],
        };
    }

    /**
     * @throws PaymentException
     */
    private function apply(PaymentTransferEntity $transfer, TransferQueryResult $result, string $currentState): void
    {
        foreach (self::stepNames($result, $currentState) as $step) {
            switch ($step) {
                case PaymentTransferSubmitter::STEP_PROCESS:
                    $this->transferService->markProcessing($transfer);

                    break;

                case PaymentTransferSubmitter::STEP_SUCCEED:
                    $this->transferService->markSucceeded($transfer);

                    break;

                case PaymentTransferSubmitter::STEP_AWAIT_CONFIRM:
                    $this->transferService->markAwaitingConfirm($transfer, $result->confirmPayload);

                    break;

                case PaymentTransferSubmitter::STEP_FAIL:
                    // the raw status of the interface is the free text the row has for a failure that
                    // arrived after the submission - the code alone often only says `FAILED`
                    $this->transferService->markFailed($transfer, $result->failureCode, $result->providerStatus);

                    break;

                case self::STEP_MARK_RETURNED:
                    // the reason column is the only place the row has for the raw status
                    $this->transferService->markReturned($transfer, $result->providerStatus);

                    break;
            }
        }
    }
}
