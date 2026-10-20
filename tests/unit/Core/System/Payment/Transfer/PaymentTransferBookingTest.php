<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

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
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Clock\MockClock;

/**
 * The booking is the last gate before money leaves the platform, and unlike the controller it can be
 * asserted directly - which is why the domain rules it guards live here and not in the action.
 *
 * Every test below states one rule of that gate: which app may pay out, through which interface and
 * channel, in which currency, and what happens when two business cases fight over one out number.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferBooking::class)]
class PaymentTransferBookingTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    private const NOW = '2026-10-21T12:00:00+00:00';

    private const PROVIDER = 'alipay';

    private const CHANNEL = 'alipay_transfer';

    private RecordingTransferRepository $repository;

    /**
     * Without an app there is nothing to pay out of: the app is the tenant of the credentials, of the
     * recipient and of the out number.
     */
    public function testBookingWithoutAnAppIsAMissingParameter(): void
    {
        $booking = $this->booking($this->app());

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload(['paymentAppId' => null]))),
            PaymentException::MISSING_PARAMETER
        );
    }

    /**
     * A blank id is the same answer as a missing one - a form that posts whitespace did not pick an app.
     */
    public function testABlankAppIdIsAMissingParameter(): void
    {
        $booking = $this->booking($this->app());

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload(['paymentAppId' => '   ']))),
            PaymentException::MISSING_PARAMETER
        );
    }

    /**
     * "Unknown" and "switched off" are one answer for the caller: neither app can pay out, and telling
     * them apart would describe rows the operator may not even see.
     *
     * @param (callable(self): ?PaymentAppEntity) $app
     */
    #[DataProvider('unavailableApps')]
    public function testAnUnavailableAppIsRefused(callable $app): void
    {
        $booking = $this->booking($app($this));

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload())),
            PaymentException::APP_NOT_FOUND
        );
    }

    /**
     * @return iterable<string, array{callable(self): ?PaymentAppEntity}>
     */
    public static function unavailableApps(): iterable
    {
        yield 'an app that does not exist' => [static fn (self $test): ?PaymentAppEntity => null];
        yield 'an app that was switched off' => [static fn (self $test): PaymentAppEntity => $test->app(active: false)];
    }

    /**
     * The ceiling exists because the amount is an integer in the smallest unit: a manual entry that is off
     * by two zeros has to be refused instead of becoming a payout nobody can take back.
     */
    #[DataProvider('invalidAmounts')]
    public function testAnAmountOutOfRangeIsRefused(?int $amount): void
    {
        $booking = $this->booking($this->payableApp());

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload(['amount' => $amount]))),
            PaymentException::TRANSFER_AMOUNT_INVALID
        );
    }

    /**
     * @return iterable<string, array{int|null}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'no amount at all' => [null];
        yield 'zero' => [0];
        yield 'a negative amount' => [-1];
        yield 'one fen above the ceiling' => [PaymentTransferDraft::MAX_AMOUNT + 1];
    }

    /**
     * An interface authorization is per app: the credentials of another tenant are not a fallback.
     */
    public function testAProviderTheAppDidNotAuthorizeIsRefused(): void
    {
        $app = $this->payableApp();
        $app->providers = [$this->authorization(self::PROVIDER, false)];

        $booking = $this->booking($app);

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload())),
            PaymentException::PROVIDER_NOT_ENABLED
        );
    }

    /**
     * An authorization for an interface the platform no longer ships is a broken app, not a payout.
     */
    public function testAnAuthorizedProviderThePlatformDoesNotShipIsRefused(): void
    {
        $booking = $this->booking($this->payableApp(), providers: false);

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload())),
            PaymentException::PROVIDER_NOT_ENABLED
        );
    }

    /**
     * Announcing a payout and not executing it is the one mismatch the caller can not see, so it is
     * refused before the intent to pay exists.
     */
    public function testAnInterfaceThatCannotPayOutIsRefused(): void
    {
        $booking = $this->booking(
            $this->payableApp(),
            providers: [$this->provider('alipay', [self::CHANNEL => ['CNY']], [])]
        );

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload())),
            PaymentException::PROVIDER_LACKS_CAPABILITY
        );
    }

    /**
     * An interface authorization does not switch on every product of that interface.
     */
    public function testAChannelTheAppSwitchedOffIsRefused(): void
    {
        $app = $this->payableApp();
        $app->channels = [$this->appChannel(self::CHANNEL, false)];

        $booking = $this->booking($app);

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload())),
            PaymentException::CHANNEL_NOT_ENABLED
        );
    }

    /**
     * An enabled app channel the interface does not declare is just as unusable - the declarations of the
     * interface are the truth, the row only records the switch.
     */
    public function testAChannelTheInterfaceDoesNotOfferIsRefused(): void
    {
        $app = $this->payableApp();
        $app->channels = [$this->appChannel('alipay_h5', true)];

        $booking = $this->booking($app);

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload())),
            PaymentException::CHANNEL_NOT_ENABLED
        );
    }

    /**
     * The currency is a code - anything else would be handed to the interface as typed.
     */
    public function testACurrencyThatIsNoCodeIsRefused(): void
    {
        $booking = $this->booking($this->payableApp());

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload(['currency' => 'CN']))),
            PaymentException::CURRENCY_NOT_SUPPORTED
        );
    }

    /**
     * A channel that settles in another currency can not be used for this payout, whatever the interface
     * claims to support.
     */
    public function testACurrencyTheChannelCannotSettleInIsRefused(): void
    {
        $booking = $this->booking(
            $this->payableApp(),
            providers: [$this->provider('alipay', [self::CHANNEL => ['USD']])]
        );

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload(['currency' => 'CNY']))),
            PaymentException::CURRENCY_NOT_SUPPORTED
        );
    }

    /**
     * A new recipient without an identity is incomplete: the identity is what the money is sent to, and the
     * matrix of which type an interface accepts belongs to the recipient service.
     *
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('incompleteRecipients')]
    public function testANewRecipientWithoutAnIdentityIsRefused(array $overrides): void
    {
        $booking = $this->booking($this->payableApp());

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload($overrides))),
            PaymentException::TRANSFER_RECIPIENT_INCOMPLETE
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function incompleteRecipients(): iterable
    {
        yield 'no identity type' => [['recipientId' => null, 'identityType' => null]];
        yield 'no identity value' => [['recipientId' => null, 'identityValue' => null]];
        yield 'neither of the two' => [['recipientId' => null, 'identityType' => null, 'identityValue' => null]];
    }

    /**
     * The hand filled out number of another payout is refused, not silently replaced: the platform would
     * otherwise answer "already booked" with a payout the operator never meant, and the money of the
     * intended one would never move.
     *
     * @param array<string, mixed> $existing
     */
    #[DataProvider('conflictingReplays')]
    public function testAnOutNoOfAnotherPayoutIsRefused(array $existing): void
    {
        $booking = $this->booking(
            $this->payableApp(),
            existing: $this->transfer($existing),
            recipient: $this->recipient(),
        );

        $this->assertRefused(
            static fn () => $booking->book(PaymentTransferDraft::fromArray(self::payload(['outNo' => 'TAKEN0001']))),
            PaymentException::TRANSFER_OUT_NO_TAKEN
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function conflictingReplays(): iterable
    {
        yield 'the number belongs to another recipient' => [['recipientId' => 'other-recipient']];
        yield 'the number belongs to another amount' => [['amount' => 2000]];
        yield 'the number belongs to another currency' => [['currency' => 'USD']];
    }

    /**
     * The idempotent replay: the same payout under the same number is booked once and returned again.
     * Nothing is written on the second call.
     */
    public function testAReplayOfTheSamePayoutReturnsTheBookedRow(): void
    {
        $existing = $this->transfer(['recipientId' => 'recipient-id', 'amount' => 1000, 'currency' => 'CNY']);

        $booking = $this->booking(
            $this->payableApp(),
            existing: $existing,
            recipient: $this->recipient(),
        );

        $result = $booking->book(PaymentTransferDraft::fromArray(self::payload(['outNo' => 'REPLAY01'])));

        static::assertSame($existing, $result);
        static::assertNull($this->repository->written, 'a replay must not book a second payout');
    }

    /**
     * The booking hand-builds the row the service stores: the note becomes metadata, the amount stays in
     * the smallest unit, the state is the initial one and the number is generated.
     */
    public function testABookedPayoutCarriesEverythingTheServiceStores(): void
    {
        $booked = $this->transfer();

        $booking = $this->booking(
            $this->payableApp(),
            booked: $booked,
            recipient: $this->recipient(),
        );

        $result = $booking->book(PaymentTransferDraft::fromArray(self::payload([
            'amount' => 1000,
            'currency' => 'cny',
            'note' => 'October rent',
        ])));

        static::assertSame($booked, $result);

        $row = $this->writtenRow();

        static::assertSame('app-id', $row['paymentAppId']);
        static::assertSame('recipient-id', $row['recipientId']);
        static::assertSame(self::PROVIDER, $row['provider']);
        static::assertSame(self::CHANNEL, $row['channel']);
        static::assertSame(1000, $row['amount']);
        // the operator types a code, the platform stores it upper case
        static::assertSame('CNY', $row['currency']);
        static::assertSame(['note' => 'October rent'], $row['metadata']);
        static::assertSame(0, $row['submitAttempts']);
        static::assertSame('state-created', $row['stateId']);
        // two characters of prefix, the moment of the booking, eight random ones - far below the 30 the
        // interfaces allow, and unique enough for two operators booking in the same second
        static::assertMatchesRegularExpression('/^TF261021120000[0-9A-F]{8}$/', $row['outNo']);
    }

    /**
     * A payout without a note stores no metadata instead of an empty array: the column is nullable and "no
     * note" has to stay indistinguishable from "note never written".
     */
    public function testAPayoutWithoutANoteStoresNoMetadata(): void
    {
        $booking = $this->booking(
            $this->payableApp(),
            booked: $this->transfer(),
            recipient: $this->recipient(),
        );

        $booking->book(PaymentTransferDraft::fromArray(self::payload(['note' => null])));

        static::assertNull($this->writtenRow()['metadata'] ?? null);
    }

    /**
     * A new recipient is created on first use and only its id reaches the transfer row - the identity, a
     * credential, is encrypted by the recipient service and never stored on the payout.
     */
    public function testANewRecipientIsCreatedForThePayout(): void
    {
        $booking = $this->booking(
            $this->payableApp(),
            booked: $this->transfer(),
            recipient: $this->recipient(),
        );

        $booking->book(PaymentTransferDraft::fromArray(self::payload([
            'recipientId' => null,
            'identityType' => 'alipay_logon_id',
            'identityValue' => 'zhangsan@example.com',
            'holderName' => 'Zhang San',
        ])));

        $row = $this->writtenRow();

        static::assertSame('recipient-id', $row['recipientId']);
        static::assertArrayNotHasKey('identityValue', $row);
    }

    /**
     * @return array<string, mixed>
     */
    private function writtenRow(): array
    {
        $rows = $this->repository->written;

        static::assertIsArray($rows);
        static::assertArrayHasKey(0, $rows);
        static::assertIsArray($rows[0]);

        return $rows[0];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function payload(array $overrides = []): array
    {
        return array_merge([
            'paymentAppId' => 'app-id',
            'provider' => self::PROVIDER,
            'channel' => self::CHANNEL,
            'recipientId' => 'recipient-id',
            'amount' => 1000,
            'currency' => 'CNY',
            'note' => null,
        ], $overrides);
    }

    /**
     * Assembles the booking with the real services and a stubbed database: the services are final and can
     * not be doubled, so only the repositories are replaced. The transfer repository is the recording one
     * below - the row it stored is the assertion of half of these tests.
     *
     * @param list<PaymentProvider>|false $providers false ships no interface at all
     */
    private function booking(
        ?PaymentAppEntity $app,
        array|false $providers = [],
        ?PaymentTransferEntity $existing = null,
        ?PaymentTransferEntity $booked = null,
        ?PaymentTransferRecipientEntity $recipient = null,
    ): PaymentTransferBooking {
        $cipher = new PaymentCredentialCipher(self::SECRET);
        $clock = new MockClock(self::NOW);

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->appResult($app));

        $recipientRepository = static::createStub(EntityRepository::class);
        $recipientRepository->method('search')->willReturn($this->recipientResult($recipient));

        $this->repository = new RecordingTransferRepository($existing, $booked);

        $recipientService = new PaymentTransferRecipientService($recipientRepository, $cipher, $clock);

        $transferService = new PaymentTransferService(
            $this->repository,
            $recipientRepository,
            new PaymentTransferStateHandler(static::createStub(StateMachineRegistry::class)),
            $recipientService,
            $cipher,
            $this->initialStateIdLoader(),
            $clock,
        );

        if ($providers === false) {
            $registry = new PaymentProviderRegistry([]);
        } else {
            $registry = new PaymentProviderRegistry($providers === []
                ? [$this->provider(self::PROVIDER, [self::CHANNEL => ['CNY']])]
                : $providers);
        }

        return new PaymentTransferBooking($appRepository, $registry, $recipientService, $transferService, $clock);
    }

    /**
     * Asserts that the money critical call refuses the payout with exactly this error code - a generic
     * "something went wrong" would hide which rule fired.
     */
    private function assertRefused(callable $action, string $expectedCode): void
    {
        try {
            $action();
            static::fail('the booking has to refuse this payout: ' . $expectedCode);
        } catch (PaymentException $exception) {
            static::assertSame($expectedCode, $exception->getErrorCode());
        }
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn('state-created');

        return $loader;
    }

    /**
     * The app as the operator picked it: authorized for the interface and with the channel switched on.
     */
    private function payableApp(): PaymentAppEntity
    {
        $app = $this->app();
        $app->providers = [$this->authorization(self::PROVIDER, true)];
        $app->channels = [$this->appChannel(self::CHANNEL, true)];

        return $app;
    }

    private function app(bool $active = true): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = 'app-id';
        $app->technicalName = 'merchant';
        $app->name = 'Merchant';
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->active = $active;
        $app->setUniqueIdentifier('app-id');

        return $app;
    }

    private function authorization(string $provider, bool $enabled): PaymentAppProviderEntity
    {
        $authorization = new PaymentAppProviderEntity();
        $authorization->id = 'authorization-' . $provider;
        $authorization->providerTechnicalName = $provider;
        $authorization->enabled = $enabled;

        return $authorization;
    }

    private function appChannel(string $channel, bool $enabled): PaymentAppChannelEntity
    {
        $row = new PaymentAppChannelEntity();
        $row->id = 'app-channel-' . $channel;
        $row->channelTechnicalName = $channel;
        $row->enabled = $enabled;

        return $row;
    }

    /**
     * @param array<string, list<string>> $channels
     * @param list<PaymentCapability> $capabilities
     */
    private function provider(string $name, array $channels, array $capabilities = [PaymentCapability::TRANSFER]): PaymentProvider
    {
        $declared = [];

        foreach ($channels as $channel => $currencies) {
            $declared[$channel] = new PaymentChannel(
                technicalName: $channel,
                providerTechnicalName: $name,
                label: $channel,
                flow: PaymentFlow::REDIRECT,
                currencies: $currencies,
            );
        }

        return new PaymentProvider(
            technicalName: $name,
            label: $name,
            credentials: [],
            channels: $declared,
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: $capabilities,
        );
    }

    private function recipient(): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'recipient-id';
        $recipient->paymentAppId = 'app-id';
        $recipient->provider = self::PROVIDER;
        $recipient->identityType = 'alipay_logon_id';
        $recipient->identityValue = (new PaymentCredentialCipher(self::SECRET))->encryptValue('zhangsan@example.com');
        $recipient->identityHash = hash('sha256', 'app-id|alipay|alipay_logon_id|zhangsan@example.com');
        $recipient->holderName = 'Zhang San';
        $recipient->setUniqueIdentifier('recipient-id');

        return $recipient;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function transfer(array $overrides = []): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->id = 'transfer-id';
        $transfer->paymentAppId = 'app-id';
        $transfer->transferNo = 'PT2610211200ABCDEF0123';
        $transfer->outNo = 'TAKEN0001';
        $transfer->recipientId = 'recipient-id';
        $transfer->provider = self::PROVIDER;
        $transfer->channel = self::CHANNEL;
        $transfer->amount = 1000;
        $transfer->currency = 'CNY';
        $transfer->submitAttempts = 0;
        $transfer->assign($overrides);
        $transfer->setUniqueIdentifier($transfer->id);

        return $transfer;
    }

    /**
     * @return EntitySearchResult<PaymentAppCollection>
     */
    private function appResult(?PaymentAppEntity $app): EntitySearchResult
    {
        $collection = new PaymentAppCollection($app === null ? [] : [$app]);

        return new EntitySearchResult('payment_app', $collection->count(), $collection, null, new Criteria(), Context::createDefaultContext());
    }

    /**
     * @return EntitySearchResult<PaymentTransferRecipientCollection>
     */
    private function recipientResult(?PaymentTransferRecipientEntity $recipient): EntitySearchResult
    {
        $collection = new PaymentTransferRecipientCollection($recipient === null ? [] : [$recipient]);

        return new EntitySearchResult('payment_transfer_recipient', $collection->count(), $collection, null, new Criteria(), Context::createDefaultContext());
    }
}

/**
 * The transfer repository of these tests: it answers the two reads the booking and the service make and
 * records what was written, so the stored row can be asserted.
 *
 * The two reads are told apart by their criteria - a lookup by id is the `getById()` after the write, every
 * other search is the idempotency lookup by out number.
 *
 * @internal
 */
#[Package('framework')]
final class RecordingTransferRepository extends EntityRepository
{
    /**
     * @var list<array<string, mixed>>|null
     */
    public ?array $written = null;

    public function __construct(
        private readonly ?PaymentTransferEntity $existing = null,
        private readonly ?PaymentTransferEntity $booked = null,
    ) {
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $entity = $criteria->getIds() === [] ? $this->existing : $this->booked;
        $collection = new PaymentTransferCollection($entity === null ? [] : [$entity]);

        return new EntitySearchResult(
            'payment_transfer',
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->written = $data;

        return new EntityWrittenContainerEvent($context, new NestedEventCollection([]), []);
    }
}
