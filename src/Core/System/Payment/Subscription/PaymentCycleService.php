<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Subscription\Schedule\PaymentCycleCalendar;
use Shopwell\Core\System\Payment\Subscription\Schedule\PaymentRetryPolicy;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;

/**
 * Books the life of a subscription cycle: schedules the period, records what the caller learned
 * from the channel and keeps the billing anchor of the subscription in sync.
 *
 * The service never talks to a channel. Whoever submits the charge or receives the notification
 * hands the outcome in; this class only decides which state that outcome means and which
 * bookkeeping belongs to it - retry back off, dunning, the rolling period.
 *
 * Idempotency is guaranteed by the unique key over (subscription, cycle number): scheduling the
 * same period twice returns the very same cycle instead of charging it twice.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentCycleService
{
    /**
     * @param EntityRepository<PaymentSubscriptionCycleCollection> $cycleRepository
     * @param EntityRepository<PaymentSubscriptionCollection> $subscriptionRepository
     */
    public function __construct(
        private readonly EntityRepository $cycleRepository,
        private readonly EntityRepository $subscriptionRepository,
        private readonly PaymentCycleStateHandler $cycleStateHandler,
        private readonly PaymentSubscriptionStateHandler $subscriptionStateHandler,
        private readonly PaymentCycleCalendar $calendar,
        private readonly PaymentRetryPolicy $retryPolicy,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Books the next period of a merchant initiated subscription.
     *
     * The period starts where the last one ended and advances period by period - a plan anchored
     * on Jan 31st ends its first period on Feb 28th and the next one on Mar 28th, never back on
     * Mar 31st.
     *
     * @throws PaymentException
     */
    public function scheduleCycle(PaymentSubscriptionEntity $subscription, PaymentPlanEntity $plan): PaymentSubscriptionCycleEntity
    {
        $now = $this->clock->now();

        $periodStart = \DateTimeImmutable::createFromInterface(
            $subscription->currentPeriodEnd ?? $subscription->trialEnd ?? $now
        );
        $periodEnd = $this->calendar->nextPeriodEnd($periodStart, $plan->intervalUnit, $plan->intervalCount);

        $cycleNo = $this->nextCycleNo($subscription->id);
        $id = Uuid::randomHex();

        $row = [
            'id' => $id,
            'subscriptionId' => $subscription->id,
            'cycleNo' => $cycleNo,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'amount' => $plan->amount,
            'currency' => $this->currencyOf($plan),
            'stateId' => $this->initialStateIdLoader->get(PaymentSubscriptionCycleStates::STATE_MACHINE),
            'outTradeNo' => $this->generateOutTradeNo($now),
            // the dal never writes the property initial value - it only consults
            // EntityDefinition::getDefaults(), and the column is not nullable without a ddl default,
            // so an insert that leaves the field out fails. Same story as paidAmount and
            // refundedAmount in PaymentOrderService::create().
            'attemptCount' => 0,
        ];

        try {
            $this->cycleRepository->create([$row], Context::createDefaultContext());
        } catch (UniqueConstraintViolationException) {
            // the unique key over the subscription and the cycle number was hit: someone else
            // already booked this period, and the cycle they created is the answer - not a second
            // charge for the same period
            $existing = $this->findByCycleNo($subscription->id, $cycleNo);

            if ($existing === null) {
                // the conflicting row is not readable in this request, so its id is not known
                // either - reporting the period is the only honest answer
                throw PaymentException::paymentCycleConflict($subscription->id, $cycleNo);
            }

            return $existing;
        }

        return $this->getById($id);
    }

    /**
     * Moves the cycle to `submitted` right before the charge request leaves the platform.
     */
    public function markSubmitted(PaymentSubscriptionCycleEntity $cycle): void
    {
        $this->cycleStateHandler->submit($cycle->id);
    }

    /**
     * Moves the cycle to `processing` once the channel accepted the charge, and remembers the
     * transaction it opened - the transaction id is the only handle a later query can use.
     */
    public function markProcessing(PaymentSubscriptionCycleEntity $cycle, ?string $providerTransactionId): void
    {
        $this->cycleStateHandler->process($cycle->id);

        if ($providerTransactionId === null) {
            return;
        }

        $this->cycleRepository->update([[
            'id' => $cycle->id,
            'providerTransactionId' => $providerTransactionId,
        ]], Context::createDefaultContext());
    }

    /**
     * Books a successful charge and rolls the subscription onto the period that was just paid: its
     * anchor becomes the end of the cycle, the dunning counters are cleared, and a subscription
     * that was `past_due` is `active` again.
     *
     * @throws PaymentException
     */
    public function markSucceeded(PaymentSubscriptionCycleEntity $cycle, string $providerTransactionId, \DateTimeInterface $paidAt): void
    {
        $this->cycleStateHandler->succeed($cycle->id);

        $this->cycleRepository->update([[
            'id' => $cycle->id,
            'providerTransactionId' => $providerTransactionId,
            'paidAt' => $paidAt,
            // a cycle that paid is done: a failure code or retry time left behind would make the
            // reconcile task believe there is still something to resolve
            'failureCode' => null,
            'failureClass' => null,
            'nextRetryAt' => null,
        ]], Context::createDefaultContext());

        $subscription = $this->getSubscription($cycle->subscriptionId);

        $this->subscriptionRepository->update([[
            'id' => $subscription->id,
            'currentPeriodStart' => $cycle->periodStart,
            'currentPeriodEnd' => $cycle->periodEnd,
            'nextBillingAt' => $cycle->periodEnd,
            'failureCount' => 0,
            'lastFailureCode' => null,
            'nextRetryAt' => null,
        ]], Context::createDefaultContext());

        if ($this->isPastDue($subscription)) {
            $this->subscriptionStateHandler->activate($subscription->id);
        }
    }

    /**
     * Books what the channel answered for a charge attempt.
     *
     * The provider is not part of the cycle - it lives on the subscription, so the subscription is
     * read here instead of being passed in: the retry bookkeeping a failure touches (failure count,
     * last failure code) is the subscription's too, and one read keeps both in step. Callers would
     * otherwise have to hand over a second, possibly stale copy of the same row.
     *
     * `attemptCount` counts the retries already consumed (0 based), which is the semantic
     * {@see PaymentRetryPolicy::nextAttemptAt()} books its ladder in - it is not a failure count.
     *
     * @throws PaymentException
     */
    public function markFailed(PaymentSubscriptionCycleEntity $cycle, ?string $failureCode): void
    {
        $subscription = $this->getSubscription($cycle->subscriptionId);
        $failureClass = $this->retryPolicy->classify($subscription->provider, $failureCode);

        if ($failureClass === PaymentRetryPolicy::UNKNOWN) {
            // the channel did not say what happened. Neither answer is safe: `failed` would
            // double charge, `succeeded` would ship without money - only a query may decide, so
            // the cycle waits in `unknown` and its raw code is kept as evidence
            $this->cycleStateHandler->markUnknown($cycle->id);

            $this->cycleRepository->update([[
                'id' => $cycle->id,
                'failureCode' => $failureCode,
                'failureClass' => PaymentSubscriptionCycleEntity::FAILURE_CLASS_UNKNOWN,
                'nextRetryAt' => null,
            ]], Context::createDefaultContext());

            return;
        }

        $nextRetryAt = null;
        if ($failureClass === PaymentRetryPolicy::RETRYABLE) {
            $nextRetryAt = $this->retryPolicy->nextAttemptAt(
                $subscription->provider,
                $failureCode,
                $cycle->attemptCount,
                $this->clock->now()
            );
        }

        // a retryable failure without a next attempt means the ladder is exhausted - that is the
        // final outcome of the cycle, not a retry that happens never
        if ($nextRetryAt === null) {
            $this->failCycleFinally($subscription, $cycle, $failureCode);

            return;
        }

        $this->cycleStateHandler->failRetryable($cycle->id);

        $this->cycleRepository->update([[
            'id' => $cycle->id,
            'failureCode' => $failureCode,
            'failureClass' => PaymentSubscriptionCycleEntity::FAILURE_CLASS_RETRYABLE,
            // the first failure consumes the first retry, so the count moves to 1 right here
            'attemptCount' => $cycle->attemptCount + 1,
            'nextRetryAt' => $nextRetryAt,
        ]], Context::createDefaultContext());
    }

    /**
     * Re-arms a cycle for one more charge attempt.
     *
     * Two transitions rather than one: the machine has no `unknown -> scheduled`, so a cycle the
     * channel reported as inconclusive first resolves to `failed_retryable`
     * (`unknown --fail--> failed_retryable`) and then goes back to the queue
     * (`--reschedule--> scheduled`). A closed or definitively failed trade moved no money, which is
     * exactly what makes the retry safe - so only call this once the channel answered definitively.
     * An in-flight trade has to stay `unknown`.
     *
     * @param bool $tradeIsDead the channel refuses the current out trade number (`ORDERCLOSED`,
     *                          `ACQ.TRADE_HAS_CLOSE`); the number is rotated, every other retry
     *                          keeps it
     *
     * @throws PaymentException
     */
    public function rearm(PaymentSubscriptionCycleEntity $cycle, bool $tradeIsDead = false): void
    {
        // the state is read fresh: a cycle that moved on since the caller loaded it must not be
        // re-armed on the strength of a stale copy
        $current = $this->getById($cycle->id);
        $state = $current->stateMachineState?->getTechnicalName();

        if ($state === PaymentSubscriptionCycleStates::STATE_UNKNOWN) {
            // the channel's answer was inconclusive, but a dead trade is an answer: resolving it to
            // `failed_retryable` is the first of the two transitions into the queue. The raw code
            // and class of the inconclusive attempt stay untouched - they are what it really was.
            $this->cycleStateHandler->failRetryable($cycle->id);
        } elseif ($state !== PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE) {
            // an in-flight or already settled cycle has no queued attempt to go back to
            throw PaymentException::paymentCycleNotRearmable($cycle->id, (string) $state);
        }

        $this->reschedule($current, $tradeIsDead);
    }

    /**
     * Books a settled refund of one period. A refund never touches the subscription: the agreement
     * stays in force, the next period is billed as usual.
     */
    public function markRefunded(PaymentSubscriptionCycleEntity $cycle, bool $partial): void
    {
        if ($partial) {
            $this->cycleStateHandler->refundPartially($cycle->id);

            return;
        }

        $this->cycleStateHandler->refund($cycle->id);
    }

    /**
     * @throws PaymentException
     */
    public function getById(string $id): PaymentSubscriptionCycleEntity
    {
        $criteria = new Criteria([$id]);
        // re-arm decides on the technical name of the state, which needs the association
        $criteria->addAssociation('stateMachineState');

        $cycle = $this->cycleRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$cycle instanceof PaymentSubscriptionCycleEntity) {
            throw PaymentException::paymentCycleNotFound($id);
        }

        return $cycle;
    }

    /**
     * Puts a retryable failure back into `scheduled`, optionally under a fresh trade number.
     *
     * The trade number is the idempotency key of the cycle and is therefore kept for every
     * ordinary retry: a request that timed out and is repeated comes back as the very same trade
     * instead of opening a second one.
     */
    private function reschedule(PaymentSubscriptionCycleEntity $cycle, bool $rotateOutTradeNo): void
    {
        $this->cycleStateHandler->reschedule($cycle->id);

        $row = [
            'id' => $cycle->id,
            'nextRetryAt' => null,
        ];

        if ($rotateOutTradeNo) {
            $row['outTradeNo'] = $this->rotateOutTradeNo($cycle->outTradeNo, $cycle->attemptCount);
        }

        $this->cycleRepository->update([$row], Context::createDefaultContext());
    }

    /**
     * A final failure is the subscription's problem, not only the cycle's: the period was not paid,
     * so the subscription enters dunning and counts the failure.
     */
    private function failCycleFinally(
        PaymentSubscriptionEntity $subscription,
        PaymentSubscriptionCycleEntity $cycle,
        ?string $failureCode,
    ): void {
        $this->cycleStateHandler->failFinal($cycle->id);

        $this->cycleRepository->update([[
            'id' => $cycle->id,
            'failureCode' => $failureCode,
            'failureClass' => PaymentSubscriptionCycleEntity::FAILURE_CLASS_FINAL,
            'nextRetryAt' => null,
        ]], Context::createDefaultContext());

        $this->subscriptionRepository->update([[
            'id' => $subscription->id,
            'failureCount' => $subscription->failureCount + 1,
            'lastFailureCode' => $failureCode,
        ]], Context::createDefaultContext());

        // only a running subscription duns: `past_due` already is the dunning state, and a
        // suspended, canceled or expired one has no transition into it
        $status = $subscription->statusMachineState?->getTechnicalName();
        if ($status === PaymentSubscriptionStates::STATE_ACTIVE || $status === PaymentSubscriptionStates::STATE_TRIALING) {
            $this->subscriptionStateHandler->fail($subscription->id);
        }
    }

    /**
     * Reads the subscription with its status state: whether a transition is legal is decided on the
     * technical name of the state, and a caller that did not load the association would leave a
     * past due subscription dunning forever.
     *
     * @throws PaymentException
     */
    private function getSubscription(string $id): PaymentSubscriptionEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('statusMachineState');

        $subscription = $this->subscriptionRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$subscription instanceof PaymentSubscriptionEntity) {
            throw PaymentException::subscriptionNotFound($id);
        }

        return $subscription;
    }

    private function isPastDue(PaymentSubscriptionEntity $subscription): bool
    {
        return $subscription->statusMachineState?->getTechnicalName() === PaymentSubscriptionStates::STATE_PAST_DUE;
    }

    /**
     * The highest cycle number of the subscription plus one - the cycle number is the identity of a
     * period, so it may never be reused, even if an earlier cycle was closed.
     */
    private function nextCycleNo(string $subscriptionId): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('subscriptionId', $subscriptionId));
        $criteria->addSorting(new FieldSorting('cycleNo', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $latest = $this->cycleRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $latest instanceof PaymentSubscriptionCycleEntity ? $latest->cycleNo + 1 : 1;
    }

    private function findByCycleNo(string $subscriptionId, int $cycleNo): ?PaymentSubscriptionCycleEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('subscriptionId', $subscriptionId),
            new EqualsFilter('cycleNo', $cycleNo)
        );
        $criteria->setLimit(1);

        $cycle = $this->cycleRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $cycle instanceof PaymentSubscriptionCycleEntity ? $cycle : null;
    }

    /**
     * The cycle records the iso code that was actually charged rather than the currency id of the
     * plan: it is a historical receipt and must not change when the plan's currency is edited.
     *
     * @throws PaymentException
     */
    private function currencyOf(PaymentPlanEntity $plan): string
    {
        $currency = $plan->currency?->getIsoCode();

        if ($currency === null) {
            throw PaymentException::missingParameter('plan.currency');
        }

        return $currency;
    }

    /**
     * 2 + 12 + 10 = 24 characters, so even the `-r<n>` suffix of a rotated retry stays below the 32
     * characters wechat allows (alipay allows 64; the strictest limit wins).
     */
    private function generateOutTradeNo(\DateTimeImmutable $now): string
    {
        return 'SC' . $now->format('ymdHis') . strtoupper(bin2hex(random_bytes(5)));
    }

    /**
     * The suffix names the attempt the new trade number is used for - the retries already consumed
     * plus the one that is about to be submitted.
     *
     * An existing suffix is stripped first: a trade may be rotated more than once, and `-r1-r2`
     * would neither be readable nor within the length the providers allow.
     */
    private function rotateOutTradeNo(string $outTradeNo, int $attemptCount): string
    {
        $base = (string) \preg_replace('/-r\d+$/', '', $outTradeNo);

        return $base . '-r' . ($attemptCount + 1);
    }
}
