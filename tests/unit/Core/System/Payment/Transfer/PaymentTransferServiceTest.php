<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferRequest;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
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
#[CoversClass(PaymentTransferService::class)]
class PaymentTransferServiceTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    private const NOW = '2026-10-18T09:00:00+00:00';

    private const CREATED_STATE = 'state-created';

    /**
     * @var list<array{entityId: string, action: string}>
     */
    private array $transitions = [];

    public function testCreateBooksANewTransfer(): void
    {
        $created = $this->transfer(['recipient' => $this->recipient()]);

        $written = [];
        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls($this->transferSearchResult([]), $this->transferSearchResult([$created]));
        $transfers->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }))
            ->willReturn($this->written());

        $result = $this->service($transfers)->create($this->app(), $this->request());

        static::assertSame($created, $result);
        static::assertSame('app-id', $written['paymentAppId']);
        static::assertSame('commission0001', $written['outNo']);
        static::assertSame('recipient-id', $written['recipientId']);
        static::assertSame('alipay', $written['provider']);
        static::assertSame('alipay_h5', $written['channel']);
        // the amount is booked as it came in - in the smallest unit of the currency
        static::assertSame(100, $written['amount']);
        static::assertSame('CNY', $written['currency']);
        static::assertSame(self::CREATED_STATE, $written['stateId']);
        // not nullable, no ddl default and the dal never writes the property initial value
        static::assertSame(0, $written['submitAttempts']);
        static::assertNull($written['expireAt']);
        static::assertSame(['source' => 'test'], $written['metadata']);
        static::assertMatchesRegularExpression('/^PT\d{12}[0-9A-F]{10}$/', $written['transferNo']);
        // nothing to keep when the channel needs no parameter of its own
        static::assertNull($written['channelParams']);
    }

    /**
     * The parameters a payout is built from have to outlive the call: the submission happens later and
     * may be repeated in a task that has no caller to ask, and the alipay adapter refuses a payout
     * whose scene is gone.
     */
    public function testCreateKeepsTheChannelParams(): void
    {
        $params = [
            'transfer_scene_name' => '佣金报酬',
            'transfer_scene_report_infos' => [['info_type' => '岗位类型', 'info_content' => '推广']],
        ];

        $written = $this->createdRowFor(
            ['channelParams' => $params],
            [$this->recipient()]
        );

        static::assertSame($params, $written['channelParams']);
    }

    /**
     * The out number is the idempotency key of the payout: asking twice for the same one returns the
     * row that exists instead of paying a second time.
     */
    public function testCreateIsIdempotentByOutNo(): void
    {
        $existing = $this->transfer();

        $transfers = static::createMock(EntityRepository::class);
        $transfers->method('search')->willReturn($this->transferSearchResult([$existing]));
        $transfers->expects($this->never())->method('create');

        static::assertSame($existing, $this->service($transfers)->create($this->app(), $this->request()));
    }

    /**
     * Wechat caps the out number at 32 characters, alipay at 64, and paypal at 30 on the ids it
     * derives from the number - the strictest limit wins, and a payout that could not be sent under
     * its number must not be booked at all.
     */
    #[DataProvider('invalidOutNos')]
    public function testAnInvalidOutNoIsRefused(string $outNo): void
    {
        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->never())->method('create');

        try {
            $this->service($transfers)->create($this->app(), $this->request(['outNo' => $outNo]));
            static::fail('an out number the interfaces refuse has to be rejected');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_OUT_NO_INVALID, $exception->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidOutNos(): iterable
    {
        yield 'empty' => [''];
        yield 'a hyphen' => ['commission-0001'];
        yield 'a space' => ['commission 0001'];
        yield 'non ascii' => ['打款0001'];
        // one character past paypal's ceiling - the derivation of the batch id would have to shorten
        // it, and a shortened id can not be matched to the out number by a notification
        yield 'too long for paypal' => [str_repeat('a', 31)];
        yield 'too long for wechat' => [str_repeat('a', 33)];
    }

    /**
     * Thirty characters is the ceiling and not one less: the limit belongs to paypal, and rejecting
     * one character earlier would refuse numbers the platform is allowed to use.
     */
    public function testAnOutNoAtTheCeilingIsAccepted(): void
    {
        $outNo = str_repeat('a', 30);

        $written = [];
        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls($this->transferSearchResult([]), $this->transferSearchResult([$this->transfer()]));
        $transfers->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }))
            ->willReturn($this->written());

        $this->service($transfers)->create($this->app(), $this->request(['outNo' => $outNo]));

        static::assertSame($outNo, $written['outNo']);
    }

    public function testANonPositiveAmountIsRefused(): void
    {
        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->never())->method('create');

        try {
            $this->service($transfers)->create($this->app(), $this->request(['amount' => 0]));
            static::fail('a payout of nothing has to be rejected');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_AMOUNT_INVALID, $exception->getErrorCode());
        }
    }

    public function testAnUnknownRecipientIsRefused(): void
    {
        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->never())->method('create');

        try {
            $this->service($transfers, $this->recipientRepository())->create($this->app(), $this->request());
            static::fail('a payout without a recipient has to be rejected');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_RECIPIENT_NOT_FOUND, $exception->getErrorCode());
        }
    }

    /**
     * A recipient is created for one interface; its identity means nothing to another one.
     */
    public function testARecipientOfAnotherInterfaceIsRefused(): void
    {
        $recipients = $this->recipientRepository([
            $this->recipient(['provider' => 'wechat', 'identityType' => 'wechat_open_id']),
        ]);

        try {
            $this->service(null, $recipients)->create($this->app(), $this->request());
            static::fail('a recipient of another interface has to be rejected');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_IDENTITY_NOT_SUPPORTED, $exception->getErrorCode());
        }
    }

    /**
     * Alipay and wechat pay out yuan only; stripe and paypal decide on their own side.
     */
    #[DataProvider('cnyOnlyProviders')]
    public function testTheCurrencyOfThePayoutIsCheckedPerInterface(string $provider, string $identityType): void
    {
        $recipients = $this->recipientRepository([
            $this->recipient(['provider' => $provider, 'identityType' => $identityType]),
        ]);

        try {
            $this->service(null, $recipients)
                ->create($this->app(), $this->request(['provider' => $provider, 'currency' => 'USD']));
            static::fail('a currency the interface does not pay out has to be rejected');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::CURRENCY_NOT_SUPPORTED, $exception->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cnyOnlyProviders(): iterable
    {
        yield 'alipay' => ['alipay', 'alipay_user_id'];
        yield 'wechat' => ['wechat', 'wechat_open_id'];
    }

    public function testWechatDemandsTheHolderNameFrom2000YuanOn(): void
    {
        $recipients = $this->recipientRepository([
            $this->recipient(['provider' => 'wechat', 'identityType' => 'wechat_open_id', 'holderName' => null]),
        ]);

        try {
            $this->service(null, $recipients)
                ->create($this->app(), $this->request(['provider' => 'wechat', 'amount' => 200000]));
            static::fail('the missing holder name has to be rejected');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_RECIPIENT_INCOMPLETE, $exception->getErrorCode());
        }
    }

    /**
     * The identity of the recipient is a credential and the payout is built from its plain text. A row
     * that can not be decrypted - a rotated application secret - is refused before the intent to move
     * money exists.
     */
    public function testARecipientWhoseIdentityCannotBeDecryptedIsRefused(): void
    {
        $recipients = $this->recipientRepository([
            $this->recipient(['identityValue' => 'not-a-ciphertext']),
        ]);

        $this->expectException(PaymentException::class);

        $this->service(null, $recipients)->create($this->app(), $this->request());
    }

    /**
     * Wechat closes a transfer the shopper never confirmed after 24 hours and refunds it - the moment
     * is booked so the query task knows when to look.
     */
    public function testWechatBooksTheConfirmWindow(): void
    {
        $written = $this->createdRowFor(
            ['provider' => 'wechat', 'channel' => 'wechat_h5'],
            [$this->recipient(['provider' => 'wechat', 'identityType' => 'wechat_open_id'])]
        );

        $expireAt = $written['expireAt'];
        static::assertInstanceOf(\DateTimeImmutable::class, $expireAt);
        static::assertSame('2026-10-19 09:00:00', $expireAt->format('Y-m-d H:i:s'));
    }

    public function testTheOtherInterfacesBookNoConfirmWindow(): void
    {
        $written = $this->createdRowFor([], [$this->recipient()]);

        static::assertNull($written['expireAt']);
    }

    /**
     * The unique key over (app, out number) is the last line of defence: a second writer between our
     * lookup and our insert, and the row they created is the answer.
     */
    public function testCreateRecoversFromAUniqueKeyRace(): void
    {
        $race = $this->transfer();

        $transfers = static::createStub(EntityRepository::class);
        $transfers->method('search')
            ->willReturnOnConsecutiveCalls($this->transferSearchResult([]), $this->transferSearchResult([$race]));
        $transfers->method('create')->willThrowException(static::createStub(UniqueConstraintViolationException::class));

        static::assertSame($race, $this->service($transfers)->create($this->app(), $this->request()));
    }

    /**
     * The conflicting row is not readable in this request, so its transfer number is unknown - the
     * out number the caller sent is the only honest reference.
     */
    public function testCreateReportsTheOutNoWhenTheConflictingTransferCannotBeReadBack(): void
    {
        $transfers = static::createStub(EntityRepository::class);
        $transfers->method('search')->willReturn($this->transferSearchResult([]));
        $transfers->method('create')->willThrowException(static::createStub(UniqueConstraintViolationException::class));

        try {
            $this->service($transfers)->create($this->app(), $this->request());
            static::fail('the conflict has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_CONFLICT, $exception->getErrorCode());
            static::assertSame('commission0001', $exception->getParameter('reference'));
        }
    }

    public function testMarkSubmittedStoresTheReferencesAndCountsTheAttempt(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_CREATED, 'submitAttempts' => 2]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))
            ->markSubmitted($transfer, 'alipay-order-1', 'tr_1A2B3C');

        static::assertSame(PaymentTransferStates::ACTION_SUBMIT, $this->transitions[0]['action']);
        static::assertSame('transfer-id', $this->transitions[0]['entityId']);
        static::assertSame('alipay-order-1', $written['providerRef']);
        static::assertSame('tr_1A2B3C', $written['providerStageRef']);
        static::assertNull($written['confirmPayload']);
        // the attempt that is about to be sent counts with it
        static::assertSame(3, $written['submitAttempts']);
        $submittedAt = $written['submittedAt'];
        static::assertInstanceOf(\DateTimeImmutable::class, $submittedAt);
        static::assertSame(self::NOW, $submittedAt->format(\DATE_ATOM));
    }

    /**
     * Wechat's `package_info` is the only way the shopper can accept the transfer - it is stored as
     * the payload the storefront reads.
     */
    public function testMarkSubmittedStoresTheWechatPackageInfo(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_CREATED]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))
            ->markSubmitted($transfer, 'wechat-bill-1', null, 'MchId=1&Package=abc');

        static::assertSame(['package_info' => 'MchId=1&Package=abc'], $written['confirmPayload']);
    }

    /**
     * Submitting a transfer a second time is not a retry but a second payout: a retry always queries
     * the interface first.
     */
    public function testAnAlreadySubmittedTransferIsRefused(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_SUBMITTED]);

        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->never())->method('update');

        try {
            $this->service($transfers)->markSubmitted($transfer, 'alipay-order-1');
            static::fail('a submitted transfer must not be submitted again');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_ALREADY_FINALIZED, $exception->getErrorCode());
            static::assertSame(PaymentTransferStates::STATE_SUBMITTED, $exception->getParameter('state'));
        }

        static::assertSame([], $this->transitions);
    }

    public function testMarkAwaitingConfirm(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_SUBMITTED]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))
            ->markAwaitingConfirm($transfer, 'MchId=1&Package=abc');

        static::assertSame(PaymentTransferStates::ACTION_AWAIT_CONFIRM, $this->transitions[0]['action']);
        static::assertSame(['package_info' => 'MchId=1&Package=abc'], $written['confirmPayload']);
    }

    /**
     * The payload may already have been stored with the submission - then the confirmation is only the
     * transition and writes nothing.
     */
    public function testMarkAwaitingConfirmWithoutPayloadOnlyTransitions(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_SUBMITTED]);

        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->never())->method('update');

        $this->service($transfers)->markAwaitingConfirm($transfer);

        static::assertSame(PaymentTransferStates::ACTION_AWAIT_CONFIRM, $this->transitions[0]['action']);
    }

    public function testMarkProcessing(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_AWAITING_USER_CONFIRM]);

        $this->service()->markProcessing($transfer);

        static::assertSame(PaymentTransferStates::ACTION_PROCESS, $this->transitions[0]['action']);
    }

    public function testMarkSucceededWritesTheSettlementAndClearsTheFailure(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_PROCESSING]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))
            ->markSucceeded($transfer, new \DateTimeImmutable('2026-10-17T10:00:00+00:00'));

        static::assertSame(PaymentTransferStates::ACTION_SUCCEED, $this->transitions[0]['action']);
        $settledAt = $written['settledAt'];
        static::assertInstanceOf(\DateTimeImmutable::class, $settledAt);
        static::assertSame('2026-10-17T10:00:00+00:00', $settledAt->format(\DATE_ATOM));
        static::assertNull($written['failureCode']);
        static::assertNull($written['failureReason']);
    }

    /**
     * Without a settlement time from the interface the platform books the moment it learned about it.
     */
    public function testMarkSucceededWithoutASettlementTimeBooksNow(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_SUBMITTED]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))->markSucceeded($transfer);

        $settledAt = $written['settledAt'];
        static::assertInstanceOf(\DateTimeImmutable::class, $settledAt);
        static::assertSame(self::NOW, $settledAt->format(\DATE_ATOM));
    }

    /**
     * One method for every origin state: a failure the interface reports after the acceptance has to
     * be bookable from `submitted`, `awaiting_user_confirm` and `processing` alike.
     */
    #[DataProvider('failureOrigins')]
    public function testMarkFailedFromEveryOpenState(string $state): void
    {
        $transfer = $this->transfer(['status' => $state]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))->markFailed($transfer, 'PAYEE_NOT_EXIST', 'account unknown');

        static::assertSame(PaymentTransferStates::ACTION_FAIL, $this->transitions[0]['action']);
        static::assertSame('PAYEE_NOT_EXIST', $written['failureCode']);
        static::assertSame('account unknown', $written['failureReason']);
        static::assertArrayNotHasKey('settledAt', $written);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function failureOrigins(): iterable
    {
        yield 'submitted' => [PaymentTransferStates::STATE_SUBMITTED];
        yield 'awaiting confirmation' => [PaymentTransferStates::STATE_AWAITING_USER_CONFIRM];
        yield 'processing' => [PaymentTransferStates::STATE_PROCESSING];
    }

    /**
     * Stripe reports a payout as `paid` and changes it to `failed` within five business days. The
     * money did not stay settled, so the timestamp has to go as well.
     */
    public function testMarkFailedRollsBackASettlement(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_SUCCEEDED]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))->markFailed($transfer, 'account_closed', 'bank closed the account');

        static::assertSame(PaymentTransferStates::ACTION_FAIL, $this->transitions[0]['action']);
        static::assertNull($written['settledAt']);
        static::assertSame('account_closed', $written['failureCode']);
    }

    #[DataProvider('returnReasons')]
    public function testMarkReturned(?string $reason): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_SUCCEEDED]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))->markReturned($transfer, $reason);

        static::assertSame(PaymentTransferStates::ACTION_MARK_RETURNED, $this->transitions[0]['action']);
        $returnedAt = $written['returnedAt'];
        static::assertInstanceOf(\DateTimeImmutable::class, $returnedAt);
        static::assertSame(self::NOW, $returnedAt->format(\DATE_ATOM));
        static::assertSame($reason, $written['failureReason']);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function returnReasons(): iterable
    {
        yield 'alipay refunds a card transfer' => ['REFUND'];
        yield 'paypal returns an unclaimed payout' => ['RETURNED'];
        yield 'without a reason' => [null];
    }

    public function testCancelWritesTheMoment(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_PROCESSING]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))->cancel($transfer);

        static::assertSame(PaymentTransferStates::ACTION_CANCEL, $this->transitions[0]['action']);
        $canceledAt = $written['canceledAt'];
        static::assertInstanceOf(\DateTimeImmutable::class, $canceledAt);
        static::assertSame(self::NOW, $canceledAt->format(\DATE_ATOM));
    }

    public function testAnUnknownTransferIsReported(): void
    {
        try {
            $this->service()->getById('transfer-id');
            static::fail('an unknown transfer has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_NOT_FOUND, $exception->getErrorCode());
        }
    }

    public function testGetByOutNoReturnsNullWhenNothingWasBooked(): void
    {
        static::assertNull($this->service()->getByOutNo($this->app(), 'commission0001'));
    }

    /**
     * The recipient is resolved through the app, so the app scope stays a boundary of the service and
     * is not re-implemented by every caller that needs a recipient.
     */
    public function testRecipientOfResolvesTheRecipientOfTheApp(): void
    {
        $recipient = $this->recipient();

        static::assertSame(
            $recipient,
            $this->service(null, $this->recipientRepository([$recipient]))
                ->recipientOf($this->app(), $this->transfer())
        );
    }

    /**
     * An attempt the platform could not observe is bookkeeping only: the state is left alone, because a
     * thrown exception says nothing about the money - the channel may have executed the request.
     */
    public function testRecordSubmitAttemptKeepsTheState(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_CREATED, 'submitAttempts' => 2]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))
            ->recordSubmitAttempt($transfer, 'timeout while waiting for the channel');

        static::assertSame(3, $written['submitAttempts']);
        static::assertSame('timeout while waiting for the channel', $written['failureReason']);
        // only the attempt is written - no state, no timestamp, no transition
        static::assertArrayNotHasKey('stateId', $written);
        static::assertArrayNotHasKey('submittedAt', $written);
        static::assertSame([], $this->transitions);
    }

    /**
     * The reason column is 255 characters wide and the message comes from the channel.
     */
    public function testRecordSubmitAttemptBoundsTheReason(): void
    {
        $transfer = $this->transfer(['status' => PaymentTransferStates::STATE_CREATED]);

        $written = [];
        $this->service($this->updateCapturingRepository($written))
            ->recordSubmitAttempt($transfer, str_repeat('x', 400));

        static::assertSame(str_repeat('x', 255), $written['failureReason']);
    }

    /**
     * Books a payout and returns the row the service handed to the repository.
     *
     * @param array<string, mixed> $request overrides of the request
     * @param list<PaymentTransferRecipientEntity> $recipients
     *
     * @return array<string, mixed>
     */
    private function createdRowFor(array $request, array $recipients): array
    {
        $created = $this->transfer();
        $written = [];

        $transfers = static::createStub(EntityRepository::class);
        $transfers->method('search')
            ->willReturnOnConsecutiveCalls($this->transferSearchResult([]), $this->transferSearchResult([$created]));
        $transfers->method('create')->willReturnCallback(function (array $data) use (&$written): EntityWrittenContainerEvent {
            $written = $data[0];

            return $this->written();
        });

        $this->service($transfers, $this->recipientRepository($recipients))->create($this->app(), $this->request($request));

        return $written;
    }

    /**
     * @param array<string, mixed> $written
     *
     * @return EntityRepository<PaymentTransferCollection>
     */
    private function updateCapturingRepository(array &$written): EntityRepository
    {
        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }))
            ->willReturn($this->written());

        return $transfers;
    }

    private function written(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
    }

    /**
     * @param EntityRepository<PaymentTransferCollection>|null $transfers
     * @param EntityRepository<PaymentTransferRecipientCollection>|null $recipients
     */
    private function service(?EntityRepository $transfers = null, ?EntityRepository $recipients = null): PaymentTransferService
    {
        $registry = static::createStub(StateMachineRegistry::class);
        $registry->method('transition')->willReturnCallback(function (Transition $transition): StateMachineStateCollection {
            $this->transitions[] = [
                'entityId' => $transition->getEntityId(),
                'action' => $transition->getTransitionName(),
            ];

            return new StateMachineStateCollection();
        });

        $cipher = $this->cipher();
        $recipients ??= $this->recipientRepository([$this->recipient()]);

        return new PaymentTransferService(
            $transfers ?? $this->transferRepository(),
            $recipients,
            new PaymentTransferStateHandler($registry),
            new PaymentTransferRecipientService($recipients, $cipher, new MockClock(self::NOW)),
            $cipher,
            $this->initialStateIdLoader(),
            new MockClock(self::NOW),
        );
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn(self::CREATED_STATE);

        return $loader;
    }

    private function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher(self::SECRET);
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = 'app-id';

        return $app;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function request(array $overrides = []): PaymentTransferRequest
    {
        $values = [
            'outNo' => 'commission0001',
            'recipientId' => 'recipient-id',
            'provider' => 'alipay',
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'metadata' => ['source' => 'test'],
            'channelParams' => [],
        ];

        foreach ($overrides as $property => $value) {
            $values[$property] = $value;
        }

        return new PaymentTransferRequest(
            outNo: $values['outNo'],
            recipientId: $values['recipientId'],
            provider: $values['provider'],
            channel: $values['channel'],
            amount: $values['amount'],
            currency: $values['currency'],
            metadata: $values['metadata'],
            channelParams: $values['channelParams'],
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function recipient(array $overrides = []): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'recipient-id';
        $recipient->paymentAppId = 'app-id';
        $recipient->provider = 'alipay';
        $recipient->identityType = 'alipay_user_id';
        $recipient->identityValue = $this->cipher()->encryptValue('2088000000000000');
        $recipient->identityHash = hash('sha256', 'app-id|alipay|alipay_user_id|2088000000000000');
        $recipient->onboardingStatus = PaymentTransferStates::ONBOARDING_REGISTERED;
        $recipient->payoutEnabled = true;
        $recipient->assign($overrides);

        $recipient->setUniqueIdentifier($recipient->id);

        return $recipient;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function transfer(array $overrides = []): PaymentTransferEntity
    {
        $state = $overrides['status'] ?? null;
        unset($overrides['status']);

        $recipient = $overrides['recipient'] ?? null;
        unset($overrides['recipient']);

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

        if ($recipient instanceof PaymentTransferRecipientEntity) {
            $transfer->recipient = $recipient;
        }

        if ($state !== null) {
            $transfer->statusMachineState = $this->stateOf($state);
        }

        return $transfer;
    }

    private function stateOf(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier($technicalName);
        $state->setTechnicalName($technicalName);

        return $state;
    }

    /**
     * @return EntityRepository<PaymentTransferCollection>
     */
    private function transferRepository(): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->transferSearchResult([]));

        return $repository;
    }

    /**
     * @param list<PaymentTransferRecipientEntity> $recipients
     *
     * @return EntityRepository<PaymentTransferRecipientCollection>
     */
    private function recipientRepository(array $recipients = []): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->recipientSearchResult($recipients));

        return $repository;
    }

    /**
     * @param list<PaymentTransferEntity> $transfers
     *
     * @return EntitySearchResult<PaymentTransferCollection>
     */
    private function transferSearchResult(array $transfers): EntitySearchResult
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

    /**
     * @param list<PaymentTransferRecipientEntity> $recipients
     *
     * @return EntitySearchResult<PaymentTransferRecipientCollection>
     */
    private function recipientSearchResult(array $recipients): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentTransferRecipientEntity::ENTITY_NAME,
            \count($recipients),
            new PaymentTransferRecipientCollection($recipients),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
