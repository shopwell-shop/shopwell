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
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationProcessor;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
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

        $this->expectExceptionObject(PaymentException::transferNotificationAppUnknown(self::APP_ID));

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
            channels: [],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $appProvider = new PaymentAppProviderEntity();
        $appProvider->setUniqueIdentifier('app-provider-id');
        $appProvider->paymentAppId = self::APP_ID;
        $appProvider->providerTechnicalName = 'alipay';
        $appProvider->enabled = true;
        $appProvider->mode = PaymentAppProviderEntity::MODE_OWN;
        $appProvider->credentials = $cipher->encrypt(['alipay_public_key' => 'key']);

        $appProviderRepository = $this->appProviderRepository($appProvider);

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $configResolver = new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            $appProviderRepository,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $cipher
        );

        $transferRepository ??= $this->transferRepository();

        $transferService = new PaymentTransferService(
            $transferRepository,
            static::createStub(EntityRepository::class),
            new PaymentTransferStateHandler(static::createStub(StateMachineRegistry::class)),
            new PaymentTransferRecipientService(static::createStub(EntityRepository::class), $cipher, new MockClock(self::NOW)),
            $cipher,
            static::createStub(InitialStateIdLoader::class),
            new MockClock(self::NOW)
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
     * @return EntityRepository<PaymentAppProviderCollection>
     */
    private function appProviderRepository(PaymentAppProviderEntity $appProvider): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_app_provider',
            1,
            new PaymentAppProviderCollection([$appProvider]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier(self::APP_ID);
        $app->id = self::APP_ID;
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
        return $this->repositoryWith(new EntitySearchResult(
            'payment_transfer',
            0,
            new PaymentTransferCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
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
