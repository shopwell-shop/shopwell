<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Subscription\PaymentCycleService;
use Shopwell\Core\System\Payment\Subscription\PaymentCycleStateHandler;
use Shopwell\Core\System\Payment\Subscription\PaymentPlanEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleStates;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionStateHandler;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionStates;
use Shopwell\Core\System\Payment\Subscription\Schedule\PaymentCycleCalendar;
use Shopwell\Core\System\Payment\Subscription\Schedule\PaymentRetryPolicy;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCycleService::class)]
class PaymentCycleServiceTest extends TestCase
{
    private const SHANGHAI = 'Asia/Shanghai';

    /**
     * 10:00 in Shanghai - the retry ladder is computed in Beijing time, but the clock hands out utc.
     */
    private const NOW = '2025-01-31T02:00:00+00:00';

    private const SCHEDULED_STATE = 'state-scheduled';

    /**
     * @var list<array{entityName: string, entityId: string, action: string}>
     */
    private array $transitions = [];

    #[DataProvider('periodAdvance')]
    public function testScheduleCycleAdvancesPeriodByPeriod(string $currentPeriodEnd, string $expectedPeriodEnd): void
    {
        $subscription = $this->subscription(['currentPeriodEnd' => $this->shanghai($currentPeriodEnd)]);
        $created = $this->cycle();

        $written = [];
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->cycleSearchResult([]),
                $this->cycleSearchResult([$created])
            );
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $result = $this->service($repository)->scheduleCycle($subscription, $this->plan());

        static::assertSame($created, $result);
        static::assertSame($currentPeriodEnd, $written['periodStart']->format('Y-m-d H:i:s'));
        // 1/31 -> 2/28 -> 3/28: the clamped day is the anchor of the next period, the original
        // anchor is never recomputed
        static::assertSame($expectedPeriodEnd, $written['periodEnd']->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function periodAdvance(): iterable
    {
        yield 'a 31st is clamped to the last day of february' => ['2025-01-31 00:00:00', '2025-02-28 00:00:00'];
        yield 'the clamped day feeds the next period' => ['2025-02-28 00:00:00', '2025-03-28 00:00:00'];
    }

    public function testScheduleCycleBooksTheFirstPeriodOfANewSubscription(): void
    {
        $subscription = $this->subscription(['currentPeriodEnd' => null, 'trialEnd' => null]);
        $created = $this->cycle(['cycleNo' => 1]);

        $written = [];
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->cycleSearchResult([]),
                $this->cycleSearchResult([$created])
            );
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $result = $this->service($repository)->scheduleCycle($subscription, $this->plan());

        static::assertSame($created, $result);
        static::assertSame('subscription-id', $written['subscriptionId']);
        static::assertSame(1, $written['cycleNo']);
        // without an anchor the period starts now - and still ends on the clamped month end
        static::assertSame('2025-01-31', $written['periodStart']->format('Y-m-d'));
        static::assertSame('2025-02-28', $written['periodEnd']->format('Y-m-d'));
        static::assertSame(1000, $written['amount']);
        static::assertSame('CNY', $written['currency']);
        static::assertSame(self::SCHEDULED_STATE, $written['stateId']);
        // not nullable, no ddl default and the dal never writes the property initial value
        static::assertSame(0, $written['attemptCount']);
        // SC + ymdHis + ten hex characters, well below the 32 the providers allow
        static::assertMatchesRegularExpression('/^SC250131020000[0-9A-F]{10}$/', $written['outTradeNo']);
        static::assertLessThanOrEqual(32, \strlen($written['outTradeNo']));
    }

    public function testScheduleCyclePrefersThePeriodEndOverTheTrialEnd(): void
    {
        $subscription = $this->subscription([
            'currentPeriodEnd' => $this->shanghai('2025-02-15 00:00:00'),
            'trialEnd' => $this->shanghai('2025-03-15 00:00:00'),
        ]);

        $written = $this->scheduleCapture($subscription);

        static::assertSame('2025-02-15', $written['periodStart']->format('Y-m-d'));
    }

    public function testScheduleCycleStartsAtTheTrialEndWhileTheSubscriptionTrials(): void
    {
        $subscription = $this->subscription([
            'currentPeriodEnd' => null,
            'trialEnd' => $this->shanghai('2025-03-10 00:00:00'),
        ]);

        $written = $this->scheduleCapture($subscription);

        static::assertSame('2025-03-10', $written['periodStart']->format('Y-m-d'));
        static::assertSame('2025-04-10', $written['periodEnd']->format('Y-m-d'));
    }

    public function testScheduleCycleNumbersTheCycleAfterTheHighestOne(): void
    {
        $written = $this->scheduleCapture(
            $this->subscription(),
            [$this->cycle(['id' => 'cycle-old', 'cycleNo' => 7])]
        );

        static::assertSame(8, $written['cycleNo']);
    }

    public function testScheduleCycleReturnsTheExistingCycleWhenTheUniqueKeyWasHit(): void
    {
        $existing = $this->cycle(['id' => 'cycle-existing']);

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->cycleSearchResult([]),
                $this->cycleSearchResult([$existing])
            );
        $repository->expects($this->once())
            ->method('create')
            ->willThrowException(static::createStub(UniqueConstraintViolationException::class));

        static::assertSame(
            $existing,
            $this->service($repository)->scheduleCycle($this->subscription(), $this->plan())
        );
    }

    /**
     * The conflicting row is not readable in this request, so its id is unknown - reporting the
     * period is the only honest answer, "not found" would be a lie.
     */
    public function testScheduleCycleReportsAConflictWhenTheCycleCannotBeReadBack(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->cycleSearchResult([]));
        $repository->expects($this->once())
            ->method('create')
            ->willThrowException(static::createStub(UniqueConstraintViolationException::class));

        $this->expectExceptionObject(PaymentException::paymentCycleConflict('subscription-id', 1));

        $this->service($repository)->scheduleCycle($this->subscription(), $this->plan());
    }

    /**
     * The cycle records the iso code that was charged; a plan read without its currency cannot
     * answer that and must not be billed.
     */
    public function testScheduleCycleThrowsWhenThePlanCurrencyIsNotLoaded(): void
    {
        $plan = $this->plan();
        $plan->currency = null;

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->cycleSearchResult([]));
        $repository->expects($this->never())->method('create');

        $this->expectExceptionObject(PaymentException::missingParameter('plan.currency'));

        $this->service($repository)->scheduleCycle($this->subscription(), $plan);
    }

    public function testMarkSubmittedMovesTheCycleToSubmitted(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->service($repository)->markSubmitted($this->cycle());

        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_SUBMIT]],
            $this->transitions
        );
    }

    public function testMarkProcessingRemembersTheTransactionOfTheChannel(): void
    {
        $written = [];
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $this->service($repository)->markProcessing($this->cycle(), 'channel-tx-1');

        static::assertSame('cycle-id', $written['id']);
        static::assertSame('channel-tx-1', $written['providerTransactionId']);
        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_PROCESS]],
            $this->transitions
        );
    }

    /**
     * A channel that accepts asynchronously does not hand out a transaction id yet - there is
     * nothing to remember, but the cycle still has to leave `submitted`.
     */
    public function testMarkProcessingWithoutATransactionIdOnlyMovesTheState(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->service($repository)->markProcessing($this->cycle(), null);

        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_PROCESS]],
            $this->transitions
        );
    }

    public function testMarkSucceededBooksTheChargeAndRollsTheSubscription(): void
    {
        $cycle = $this->cycle([
            'periodStart' => $this->shanghai('2025-01-31 00:00:00'),
            'periodEnd' => $this->shanghai('2025-02-28 00:00:00'),
        ]);
        $subscription = $this->subscription(['status' => PaymentSubscriptionStates::STATE_ACTIVE]);

        $cycleWritten = [];
        $cycleRepository = static::createMock(EntityRepository::class);
        $cycleRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$cycleWritten): bool {
                $cycleWritten = $data[0];

                return true;
            }));

        $subscriptionWritten = [];
        $subscriptionRepository = static::createMock(EntityRepository::class);
        $subscriptionRepository->method('search')->willReturn($this->subscriptionSearchResult($subscription));
        $subscriptionRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$subscriptionWritten): bool {
                $subscriptionWritten = $data[0];

                return true;
            }));

        $this->service($cycleRepository, $subscriptionRepository)->markSucceeded(
            $cycle,
            'channel-tx-1',
            $this->shanghai('2025-02-01 09:00:00')
        );

        static::assertSame('cycle-id', $cycleWritten['id']);
        static::assertSame('channel-tx-1', $cycleWritten['providerTransactionId']);
        static::assertSame('2025-02-01 09:00:00', $cycleWritten['paidAt']->format('Y-m-d H:i:s'));
        // a paid cycle must not look like it still needs a query
        static::assertNull($cycleWritten['failureCode']);
        static::assertNull($cycleWritten['failureClass']);
        static::assertNull($cycleWritten['nextRetryAt']);

        static::assertSame('subscription-id', $subscriptionWritten['id']);
        static::assertSame('2025-01-31', $subscriptionWritten['currentPeriodStart']->format('Y-m-d'));
        static::assertSame('2025-02-28', $subscriptionWritten['currentPeriodEnd']->format('Y-m-d'));
        // the next period is scheduled from the period that was just paid
        static::assertSame('2025-02-28', $subscriptionWritten['nextBillingAt']->format('Y-m-d'));
        static::assertSame(0, $subscriptionWritten['failureCount']);
        static::assertNull($subscriptionWritten['lastFailureCode']);
        static::assertNull($subscriptionWritten['nextRetryAt']);

        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_SUCCEED]],
            $this->transitions
        );
    }

    /**
     * A subscription that was dunning comes back to `active` once the outstanding period is paid.
     */
    public function testMarkSucceededReactivatesAPastDueSubscription(): void
    {
        $subscription = $this->subscription(['status' => PaymentSubscriptionStates::STATE_PAST_DUE]);

        $subscriptionRepository = static::createMock(EntityRepository::class);
        $subscriptionRepository->method('search')->willReturn($this->subscriptionSearchResult($subscription));
        $subscriptionRepository->method('update');

        $this->service(null, $subscriptionRepository)->markSucceeded(
            $this->cycle(),
            'channel-tx-1',
            $this->shanghai('2025-02-01 09:00:00')
        );

        static::assertSame(
            [
                ['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_SUCCEED],
                ['entityName' => PaymentSubscriptionEntity::ENTITY_NAME, 'entityId' => 'subscription-id', 'action' => PaymentSubscriptionStates::ACTION_ACTIVATE],
            ],
            $this->transitions
        );
    }

    #[DataProvider('retryLadder')]
    public function testMarkFailedBooksTheBackoffOfTheRetryLadder(int $consumed, string $expectedNextRetryAt): void
    {
        $cycle = $this->cycle(['attemptCount' => $consumed]);
        $subscription = $this->subscription();

        $written = [];
        $cycleRepository = static::createMock(EntityRepository::class);
        $cycleRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $subscriptionRepository = static::createMock(EntityRepository::class);
        $subscriptionRepository->method('search')->willReturn($this->subscriptionSearchResult($subscription));
        $subscriptionRepository->expects($this->never())->method('update');

        $this->service($cycleRepository, $subscriptionRepository)->markFailed($cycle, 'ACQ.BUYER_BALANCE_NOT_ENOUGH');

        static::assertSame('cycle-id', $written['id']);
        static::assertSame('ACQ.BUYER_BALANCE_NOT_ENOUGH', $written['failureCode']);
        static::assertSame(PaymentSubscriptionCycleEntity::FAILURE_CLASS_RETRYABLE, $written['failureClass']);
        // the attempt count counts consumed retries, so the first failure already consumes one
        static::assertSame($consumed + 1, $written['attemptCount']);
        static::assertSame(
            $expectedNextRetryAt,
            $written['nextRetryAt']->setTimezone(new \DateTimeZone(self::SHANGHAI))->format('Y-m-d H:i:s')
        );

        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE]],
            $this->transitions
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function retryLadder(): iterable
    {
        yield 'first failure waits one day' => [0, '2025-02-01 10:00:00'];
        yield 'second failure waits three days' => [1, '2025-02-03 10:00:00'];
        yield 'third and later failures wait a week' => [2, '2025-02-07 10:00:00'];
    }

    /**
     * The fifth retry was the last one - the policy returns null and the retryable failure becomes
     * the final outcome of the cycle instead of a retry that never happens.
     */
    public function testMarkFailedEndsFinallyWhenTheRetryLadderIsExhausted(): void
    {
        $cycle = $this->cycle(['attemptCount' => PaymentRetryPolicy::MAX_RETRIES]);
        $subscription = $this->subscription();

        $cycleWritten = $this->failFinally($cycle, $subscription, 'ACQ.BUYER_BALANCE_NOT_ENOUGH');

        static::assertSame(PaymentSubscriptionCycleEntity::FAILURE_CLASS_FINAL, $cycleWritten['failureClass']);
        static::assertNull($cycleWritten['nextRetryAt']);
        // a final failure consumes no retry - there is none left
        static::assertArrayNotHasKey('attemptCount', $cycleWritten);
    }

    public function testMarkFailedEndsFinallyOnAFinalCode(): void
    {
        $cycle = $this->cycle();
        $subscription = $this->subscription();

        $cycleWritten = $this->failFinally($cycle, $subscription, 'ACQ.AGREEMENT_INVALID');

        static::assertSame('ACQ.AGREEMENT_INVALID', $cycleWritten['failureCode']);
        static::assertSame(PaymentSubscriptionCycleEntity::FAILURE_CLASS_FINAL, $cycleWritten['failureClass']);
    }

    /**
     * Alipay docs advise a single retry for its generic failure - the second one is final.
     */
    public function testMarkFailedTreatsTheSecondAlipayPaymentFailAsFinal(): void
    {
        $cycle = $this->cycle(['attemptCount' => 1]);

        $cycleWritten = $this->failFinally($cycle, $this->subscription(), 'ACQ.PAYMENT_FAIL');

        static::assertSame(PaymentSubscriptionCycleEntity::FAILURE_CLASS_FINAL, $cycleWritten['failureClass']);
    }

    /**
     * The subscription counts the missed period and enters dunning - the mandate is still valid, so
     * the subscriber can fix the payment method.
     */
    public function testMarkFailedFinallyPutsTheSubscriptionIntoDunning(): void
    {
        $subscription = $this->subscription(['failureCount' => 2]);

        $cycleRepository = static::createMock(EntityRepository::class);
        $cycleRepository->method('update');

        $subscriptionWritten = [];
        $subscriptionRepository = static::createMock(EntityRepository::class);
        $subscriptionRepository->method('search')->willReturn($this->subscriptionSearchResult($subscription));
        $subscriptionRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$subscriptionWritten): bool {
                $subscriptionWritten = $data[0];

                return true;
            }));

        $this->service($cycleRepository, $subscriptionRepository)
            ->markFailed($this->cycle(), 'ACQ.AGREEMENT_INVALID');

        static::assertSame('subscription-id', $subscriptionWritten['id']);
        static::assertSame(3, $subscriptionWritten['failureCount']);
        static::assertSame('ACQ.AGREEMENT_INVALID', $subscriptionWritten['lastFailureCode']);

        static::assertSame(
            [
                ['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL],
                ['entityName' => PaymentSubscriptionEntity::ENTITY_NAME, 'entityId' => 'subscription-id', 'action' => PaymentSubscriptionStates::ACTION_FAIL],
            ],
            $this->transitions
        );
    }

    /**
     * A subscription that is already dunning stays there: the state machine has no past_due ->
     * past_due transition, and the failure is still counted.
     */
    public function testMarkFailedFinallyDoesNotDunAnAlreadyPastDueSubscription(): void
    {
        $subscription = $this->subscription([
            'status' => PaymentSubscriptionStates::STATE_PAST_DUE,
            'failureCount' => 1,
        ]);

        $cycleRepository = static::createMock(EntityRepository::class);
        $cycleRepository->method('update');

        $subscriptionWritten = [];
        $subscriptionRepository = static::createMock(EntityRepository::class);
        $subscriptionRepository->method('search')->willReturn($this->subscriptionSearchResult($subscription));
        $subscriptionRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$subscriptionWritten): bool {
                $subscriptionWritten = $data[0];

                return true;
            }));

        $this->service($cycleRepository, $subscriptionRepository)
            ->markFailed($this->cycle(), 'ACQ.AGREEMENT_INVALID');

        static::assertSame(2, $subscriptionWritten['failureCount']);
        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL]],
            $this->transitions
        );
    }

    /**
     * `unknown` must stay unknown: neither answer is safe, only a query of the channel may decide.
     * The raw code is kept as evidence.
     */
    #[DataProvider('unknownFailures')]
    public function testMarkFailedKeepsAnUnknownAnswerInUnknown(?string $failureCode, ?string $expectedStoredCode): void
    {
        $cycleRepository = static::createMock(EntityRepository::class);
        $cycleRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use ($expectedStoredCode): bool {
                static::assertSame($expectedStoredCode, $data[0]['failureCode']);
                static::assertSame(PaymentSubscriptionCycleEntity::FAILURE_CLASS_UNKNOWN, $data[0]['failureClass']);
                static::assertNull($data[0]['nextRetryAt']);
                static::assertArrayNotHasKey('attemptCount', $data[0]);

                return true;
            }));

        $subscriptionRepository = static::createMock(EntityRepository::class);
        $subscriptionRepository->method('search')->willReturn($this->subscriptionSearchResult($this->subscription()));
        // an unknown answer is no failure yet - the subscription is not touched
        $subscriptionRepository->expects($this->never())->method('update');

        $this->service($cycleRepository, $subscriptionRepository)->markFailed($this->cycle(), $failureCode);

        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_MARK_UNKNOWN]],
            $this->transitions
        );
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function unknownFailures(): iterable
    {
        yield 'system error of alipay' => ['ACQ.SYSTEM_ERROR', 'ACQ.SYSTEM_ERROR'];
        yield 'a failure without a code' => [null, null];
        // the trade is closed on the channel side, but the cycle is not a failure for that reason
        yield 'closed wechat order' => ['ORDERCLOSED', 'ORDERCLOSED'];
    }

    public function testRearmQueuesAFailedCycleAgain(): void
    {
        $cycle = $this->cycle([
            'attemptCount' => 1,
            'state' => PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE,
        ]);

        $written = $this->rearmCapture($cycle);

        static::assertSame('cycle-id', $written['id']);
        static::assertNull($written['nextRetryAt']);
        // the trade number is the idempotency key - a second one would be a second charge
        static::assertArrayNotHasKey('outTradeNo', $written);

        // failed_retryable is already queuable, one transition is enough
        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_RESCHEDULE]],
            $this->transitions
        );
    }

    /**
     * The machine has no `unknown -> scheduled`: an inconclusive cycle the channel has since
     * answered for resolves to `failed_retryable` first and only then returns to the queue.
     */
    public function testRearmResolvesAnUnknownCycleInTwoTransitions(): void
    {
        $cycle = $this->cycle(['state' => PaymentSubscriptionCycleStates::STATE_UNKNOWN]);

        $written = $this->rearmCapture($cycle);

        static::assertSame('cycle-id', $written['id']);
        static::assertNull($written['nextRetryAt']);
        static::assertSame(
            [
                ['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE],
                ['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => PaymentSubscriptionCycleStates::ACTION_RESCHEDULE],
            ],
            $this->transitions
        );
    }

    public function testRearmRotatesTheTradeNumberOfADeadTrade(): void
    {
        $cycle = $this->cycle([
            'attemptCount' => 0,
            'outTradeNo' => 'SC250131020000ABCDEF0123',
            'state' => PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE,
        ]);

        $written = $this->rearmCapture($cycle, true);

        static::assertSame('SC250131020000ABCDEF0123-r1', $written['outTradeNo']);
        static::assertLessThanOrEqual(32, \strlen($written['outTradeNo']));
    }

    /**
     * The trade may be rotated more than once: an existing suffix is stripped before the new one is
     * appended, otherwise `-r1-r2` would grow the number past what the providers accept.
     */
    public function testRearmRotationStaysIdempotent(): void
    {
        $cycle = $this->cycle([
            'attemptCount' => 0,
            'outTradeNo' => 'SC250131020000ABCDEF0123',
            'state' => PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE,
        ]);

        $first = $this->rearmCapture($cycle, true)['outTradeNo'];
        $cycle->outTradeNo = $first;

        $second = $this->rearmCapture($cycle, true)['outTradeNo'];

        static::assertSame($first, $second);
        static::assertStringNotContainsString('-r1-r', $second);
        static::assertLessThanOrEqual(32, \strlen($second));
    }

    /**
     * The suffix always names the attempt the new number is used for, no matter which suffix the
     * previous rotation left behind.
     */
    public function testRearmRotationReplacesAnOldSuffix(): void
    {
        $cycle = $this->cycle([
            'attemptCount' => 2,
            'outTradeNo' => 'SC250131020000ABCDEF0123-r1',
            'state' => PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE,
        ]);

        $written = $this->rearmCapture($cycle, true);

        static::assertSame('SC250131020000ABCDEF0123-r3', $written['outTradeNo']);
    }

    /**
     * An in-flight trade must stay unknown until a query resolves it - re-arming it could charge
     * the same period twice.
     */
    public function testRearmRefusesAnInFlightCycle(): void
    {
        $cycle = $this->cycle(['state' => PaymentSubscriptionCycleStates::STATE_PROCESSING]);

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->cycleSearchResult([$cycle]));
        $repository->expects($this->never())->method('update');

        $this->expectExceptionObject(
            PaymentException::paymentCycleNotRearmable('cycle-id', PaymentSubscriptionCycleStates::STATE_PROCESSING)
        );

        $this->service($repository)->rearm($cycle, true);
    }

    public function testRearmRefusesASucceededCycle(): void
    {
        $cycle = $this->cycle(['state' => PaymentSubscriptionCycleStates::STATE_SUCCEEDED]);

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->cycleSearchResult([$cycle]));
        $repository->expects($this->never())->method('update');

        $this->expectExceptionObject(
            PaymentException::paymentCycleNotRearmable('cycle-id', PaymentSubscriptionCycleStates::STATE_SUCCEEDED)
        );

        $this->service($repository)->rearm($cycle);
    }

    #[DataProvider('refunds')]
    public function testMarkRefundedBooksTheRefundOfTheCycle(bool $partial, string $expectedAction): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->service($repository)->markRefunded($this->cycle(), $partial);

        static::assertSame(
            [['entityName' => PaymentSubscriptionCycleEntity::ENTITY_NAME, 'entityId' => 'cycle-id', 'action' => $expectedAction]],
            $this->transitions
        );
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function refunds(): iterable
    {
        yield 'partial refund' => [true, PaymentSubscriptionCycleStates::ACTION_REFUND_PARTIALLY];
        yield 'full refund' => [false, PaymentSubscriptionCycleStates::ACTION_REFUND];
    }

    /**
     * Runs markFailed() with two mock repositories and returns the row written to the cycle.
     *
     * @return array<string, mixed>
     */
    private function failFinally(
        PaymentSubscriptionCycleEntity $cycle,
        PaymentSubscriptionEntity $subscription,
        ?string $failureCode,
    ): array {
        $written = [];
        $cycleRepository = static::createMock(EntityRepository::class);
        $cycleRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $subscriptionRepository = static::createMock(EntityRepository::class);
        $subscriptionRepository->method('search')->willReturn($this->subscriptionSearchResult($subscription));
        $subscriptionRepository->method('update');

        $this->service($cycleRepository, $subscriptionRepository)->markFailed($cycle, $failureCode);

        return $written;
    }

    /**
     * @return array<string, mixed>
     */
    private function rearmCapture(PaymentSubscriptionCycleEntity $cycle, bool $tradeIsDead = false): array
    {
        $written = [];
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->cycleSearchResult([$cycle]));
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $this->service($repository)->rearm($cycle, $tradeIsDead);

        return $written;
    }

    /**
     * @param list<PaymentSubscriptionCycleEntity> $existing
     *
     * @return array<string, mixed>
     */
    private function scheduleCapture(PaymentSubscriptionEntity $subscription, array $existing = []): array
    {
        $created = $this->cycle();

        $written = [];
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->cycleSearchResult($existing),
                $this->cycleSearchResult([$created])
            );
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $this->service($repository)->scheduleCycle($subscription, $this->plan());

        return $written;
    }

    private function service(
        ?EntityRepository $cycleRepository = null,
        ?EntityRepository $subscriptionRepository = null,
    ): PaymentCycleService {
        $registry = static::createStub(StateMachineRegistry::class);
        $registry->method('transition')->willReturnCallback(function (Transition $transition): StateMachineStateCollection {
            $this->transitions[] = [
                'entityName' => $transition->getEntityName(),
                'entityId' => $transition->getEntityId(),
                'action' => $transition->getTransitionName(),
            ];

            return new StateMachineStateCollection();
        });

        return new PaymentCycleService(
            $cycleRepository ?? $this->cycleRepository(),
            $subscriptionRepository ?? $this->subscriptionRepository(),
            new PaymentCycleStateHandler($registry),
            new PaymentSubscriptionStateHandler($registry),
            new PaymentCycleCalendar(),
            new PaymentRetryPolicy(),
            $this->initialStateIdLoader(),
            new MockClock(self::NOW),
        );
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn(self::SCHEDULED_STATE);

        return $loader;
    }

    /**
     * @param list<PaymentSubscriptionCycleEntity> $cycles
     *
     * @return EntityRepository<PaymentSubscriptionCycleCollection>
     */
    private function cycleRepository(array $cycles = []): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->cycleSearchResult($cycles));

        return $repository;
    }

    /**
     * @return EntityRepository<PaymentSubscriptionCollection>
     */
    private function subscriptionRepository(?PaymentSubscriptionEntity $subscription = null): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(
            $this->subscriptionSearchResult($subscription ?? $this->subscription())
        );

        return $repository;
    }

    /**
     * @param list<PaymentSubscriptionCycleEntity> $cycles
     *
     * @return EntitySearchResult<PaymentSubscriptionCycleCollection>
     */
    private function cycleSearchResult(array $cycles): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentSubscriptionCycleEntity::ENTITY_NAME,
            \count($cycles),
            new PaymentSubscriptionCycleCollection($cycles),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<PaymentSubscriptionCollection>
     */
    private function subscriptionSearchResult(PaymentSubscriptionEntity $subscription): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentSubscriptionEntity::ENTITY_NAME,
            1,
            new PaymentSubscriptionCollection([$subscription]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function cycle(array $overrides = []): PaymentSubscriptionCycleEntity
    {
        $state = $overrides['state'] ?? null;
        unset($overrides['state']);

        $cycle = new PaymentSubscriptionCycleEntity();
        $cycle->id = 'cycle-id';
        $cycle->subscriptionId = 'subscription-id';
        $cycle->cycleNo = 1;
        $cycle->periodStart = $this->shanghai('2025-01-01 00:00:00');
        $cycle->periodEnd = $this->shanghai('2025-02-01 00:00:00');
        $cycle->amount = 1000;
        $cycle->currency = 'CNY';
        $cycle->stateId = 'state-scheduled';
        $cycle->outTradeNo = 'SC250101000000ABCDEF0123';

        foreach ($overrides as $property => $value) {
            $cycle->{$property} = $value;
        }

        $cycle->setUniqueIdentifier($cycle->id);

        if ($state !== null) {
            $cycle->stateMachineState = $this->stateOf($state);
        }

        return $cycle;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function subscription(array $overrides = []): PaymentSubscriptionEntity
    {
        $status = $overrides['status'] ?? PaymentSubscriptionStates::STATE_ACTIVE;
        unset($overrides['status']);

        $subscription = new PaymentSubscriptionEntity();
        $subscription->id = 'subscription-id';
        $subscription->subscriptionNo = 'PS0001';
        $subscription->planId = 'plan-id';
        $subscription->customerId = 'customer-id';
        $subscription->salesChannelId = 'sales-channel-id';
        $subscription->billingMode = PaymentPlanEntity::BILLING_MODE_MERCHANT_INITIATED;
        $subscription->provider = 'alipay';
        $subscription->channel = 'alipay_h5';
        $subscription->statusId = 'status-active';
        $subscription->failureCount = 0;

        foreach ($overrides as $property => $value) {
            $subscription->{$property} = $value;
        }

        $subscription->setUniqueIdentifier($subscription->id);
        $subscription->statusMachineState = $this->stateOf($status);

        return $subscription;
    }

    private function stateOf(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier($technicalName);
        $state->setTechnicalName($technicalName);

        return $state;
    }

    private function plan(): PaymentPlanEntity
    {
        $currency = new CurrencyEntity();
        $currency->setUniqueIdentifier('currency-id');
        $currency->setIsoCode('CNY');

        $plan = new PaymentPlanEntity();
        $plan->id = 'plan-id';
        $plan->technicalName = 'monthly';
        $plan->salesChannelId = 'sales-channel-id';
        $plan->billingMode = PaymentPlanEntity::BILLING_MODE_MERCHANT_INITIATED;
        $plan->provider = 'alipay';
        $plan->channel = 'alipay_h5';
        $plan->intervalUnit = PaymentPlanEntity::INTERVAL_UNIT_MONTH;
        $plan->intervalCount = 1;
        $plan->amount = 1000;
        $plan->currencyId = 'currency-id';
        $plan->trialDays = 0;
        $plan->currency = $currency;
        $plan->setUniqueIdentifier('plan-id');

        return $plan;
    }

    private function shanghai(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone(self::SHANGHAI));
    }
}
