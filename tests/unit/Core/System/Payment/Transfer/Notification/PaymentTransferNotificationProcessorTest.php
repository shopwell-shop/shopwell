<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\Request\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\Request\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationProcessor;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Account\_fixtures\PaymentAccountLockStub;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Shopwell\Tests\Unit\Core\System\Payment\Transfer\PaymentTransferRulesFixture;
use Symfony\Component\Clock\MockClock;

/**
 * A payout notification only triggers a query: the processor proves the body came from the interface,
 * reads the payout number out of it, finds the payout and lets the reconciler ask the interface. The
 * payout itself is never written from the body - that is what these tests pin down.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferNotificationProcessor::class)]
class PaymentTransferNotificationProcessorTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    private const APP_ID = 'app-id';

    public function testAnUnknownAppIsRejectedBeforeAnythingIsStored(): void
    {
        $notificationRepository = static::createMock(EntityRepository::class);
        $notificationRepository->expects($this->never())->method('create');
        $notificationRepository->expects($this->never())->method('update');

        $processor = $this->processor($notificationRepository, $this->emptyAppRepository(), $this->verifier(true, 'PO-1'));

        $this->expectExceptionObject(PaymentTransferException::transferNotificationAppUnknown(self::APP_ID));

        $processor->process('alipay', self::APP_ID, '{}', [], '1.2.3.4');
    }

    public function testAnInvalidSignatureIsRecordedAndNothingElseHappens(): void
    {
        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_INVALID_SIGNATURE, $row['processResult']);
                static::assertFalse($row['signatureValid']);
                static::assertFalse($row['processed']);
                static::assertNull($row['paymentTransferId']);
            }
        );

        $processor = $this->processor($notificationRepository, $this->appRepository(), $this->verifier(false, 'PO-1'));

        $processor->process('alipay', self::APP_ID, '{"out_biz_no":"PO-1"}', ['Authorization' => 'Bearer secret'], '1.2.3.4');
    }

    public function testANotificationWithoutAPayoutNumberIsUnmatched(): void
    {
        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_UNMATCHED, $row['processResult']);
                static::assertTrue($row['signatureValid']);
                static::assertFalse($row['processed']);
                static::assertNull($row['paymentTransferId']);
            }
        );

        $processor = $this->processor($notificationRepository, $this->appRepository(), $this->verifier(true, null));

        $processor->process('alipay', self::APP_ID, '{}', [], null);
    }

    public function testAPayoutOfAnotherAppIsUnmatched(): void
    {
        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_UNMATCHED, $row['processResult']);
                static::assertTrue($row['signatureValid']);
                static::assertNull($row['paymentTransferId']);
            }
        );

        $processor = $this->processor(
            $notificationRepository,
            $this->appRepository(),
            $this->verifier(true, 'PO-1'),
            $this->transferRepository()
        );

        $processor->process('alipay', self::APP_ID, '{"out_biz_no":"PO-1"}', [], null);
    }

    /**
     * The result of the notification is the result of the query: an answer the channel could not give
     * is no success and no failure, so it is recorded as ignored and not as done.
     *
     * @param string $expected the result the notification row gets
     */
    #[DataProvider('queryResultProvider')]
    public function testTheAnswerOfTheQueryDecidesTheResult(string $state, string $expected): void
    {
        static::assertSame($expected, PaymentTransferNotificationProcessor::resultOf(new TransferQueryResult($state, '')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function queryResultProvider(): iterable
    {
        yield 'no answer' => [TransferResult::STATE_UNKNOWN, PaymentNotificationEntity::RESULT_IGNORED];
        yield 'accepted' => [TransferResult::STATE_ACCEPTED, PaymentNotificationEntity::RESULT_OK];
        yield 'processing' => [TransferResult::STATE_PROCESSING, PaymentNotificationEntity::RESULT_OK];
        yield 'succeeded' => [TransferResult::STATE_SUCCEEDED, PaymentNotificationEntity::RESULT_OK];
        yield 'failed' => [TransferResult::STATE_FAILED, PaymentNotificationEntity::RESULT_OK];
        yield 'returned' => [TransferResult::STATE_RETURNED, PaymentNotificationEntity::RESULT_OK];
    }

    /**
     * @param EntityRepository<PaymentNotificationCollection> $notificationRepository
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentTransferCollection>|null $transferRepository
     */
    private function processor(
        EntityRepository $notificationRepository,
        EntityRepository $appRepository,
        PaymentTransferNotificationVerifierInterface $verifier,
        ?EntityRepository $transferRepository = null
    ): PaymentTransferNotificationProcessor {
        $cipher = new PaymentCredentialCipher('test-secret');

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            products: [],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $configResolver = PaymentTransferRulesFixture::boundResolver($provider, $cipher);

        $transferRepository ??= $this->transferRepository();

        $transferService = new PaymentTransferService(
            $transferRepository,
            static::createStub(EntityRepository::class),
            new PaymentTransferStateHandler(static::createStub(StateMachineRegistry::class)),
            new PaymentTransferRecipientService(static::createStub(EntityRepository::class), $cipher, new MockClock(self::NOW), PaymentTransferRulesFixture::registry()),
            $cipher,
            static::createStub(InitialStateIdLoader::class),
            new MockClock(self::NOW),
            PaymentTransferRulesFixture::registry(),
            NumberRangeFixture::generator('PT1001'),
            PaymentTransferRulesFixture::resolvedConfig(),
            new PaymentAccountLockStub(),
        );

        $reconciler = new PaymentTransferReconciler(
            $configResolver,
            $transferService,
            $transferRepository,
            static::createStub(EntityRepository::class),
            new MockClock(self::NOW),
            static::createStub(LoggerInterface::class)
        );

        return new PaymentTransferNotificationProcessor(
            $notificationRepository,
            $appRepository,
            new PaymentTransferNotificationVerifierRegistry([$verifier]),
            $configResolver,
            $transferService,
            $reconciler
        );
    }

    private function verifier(bool $valid, ?string $outNo): PaymentTransferNotificationVerifierInterface
    {
        $verifier = static::createStub(PaymentTransferNotificationVerifierInterface::class);
        $verifier->method('provider')->willReturn('alipay');
        $verifier->method('verify')->willReturn($valid);
        $verifier->method('outNoOf')->willReturn($outNo);

        return $verifier;
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier(self::APP_ID);
        $app->id = self::APP_ID;
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->technicalName = 'test-app';

        return $this->repositoryWith(new EntitySearchResult(
            'payment_app',
            1,
            new PaymentAppCollection([$app]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function emptyAppRepository(): EntityRepository
    {
        return $this->repositoryWith(new EntitySearchResult(
            'payment_app',
            0,
            new PaymentAppCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
    }

    /**
     * @return EntityRepository<PaymentTransferCollection>
     */
    private function transferRepository(): EntityRepository
    {
        $transfer = new PaymentTransferEntity();
        $transfer->id = 'other-transfer';
        $transfer->setUniqueIdentifier($transfer->id);
        $transfer->paymentAppId = self::APP_ID;
        $transfer->accountId = 'account-id';
        $transfer->provider = 'alipay';
        $select = static function (Criteria $criteria) use ($transfer): PaymentTransferCollection {
            foreach ($criteria->getFilters() as $filter) {
                if ($filter instanceof EqualsFilter && $filter->getField() === 'outNo') {
                    return new PaymentTransferCollection();
                }
            }

            return new PaymentTransferCollection([$transfer]);
        };

        return StaticEntityRepository::of(PaymentTransferCollection::class, [$select, $select]);
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntitySearchResult<TCollection> $result
     *
     * @return EntityRepository<TCollection>
     */
    private function repositoryWith(EntitySearchResult $result): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        return $repository;
    }

    /**
     * @return EntityRepository<PaymentNotificationCollection>
     */
    private function notificationRepository(?\Closure $onUpdate = null): EntityRepository
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())->method('create');

        $update = $repository->expects($this->once())->method('update');
        if ($onUpdate !== null) {
            $update->with(static::callback(static function (array $data) use ($onUpdate): bool {
                $onUpdate($data[0]);

                return true;
            }));
        }

        return $repository;
    }
}
