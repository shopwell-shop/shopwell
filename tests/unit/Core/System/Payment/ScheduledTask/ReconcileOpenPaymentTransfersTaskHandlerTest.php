<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentTransfersTaskHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReconcileOpenPaymentTransfersTaskHandler::class)]
#[CoversClass(PaymentTransferReconciler::class)]
class ReconcileOpenPaymentTransfersTaskHandlerTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    public function testRunAsksTheInterfacesAboutTheDuePayouts(): void
    {
        // the state lookup is the first read of the batch, the transfer search the second - both are
        // the proof the handler ran the reconciliation and not something else
        $stateRepository = static::createMock(EntityRepository::class);
        $stateRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'state_machine_state',
                4,
                new StateMachineStateCollection([
                    $this->state(PaymentTransferStates::STATE_SUBMITTED),
                    $this->state(PaymentTransferStates::STATE_PROCESSING),
                    $this->state(PaymentTransferStates::STATE_AWAITING_USER_CONFIRM),
                    $this->state(PaymentTransferStates::STATE_SUCCEEDED),
                ]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        $transferRepository = static::createMock(EntityRepository::class);
        $transferRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                PaymentTransferEntity::ENTITY_NAME,
                0,
                new PaymentTransferCollection([]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        $handler = new ReconcileOpenPaymentTransfersTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->reconciler($transferRepository, $stateRepository)
        );

        $handler->run();
    }

    /**
     * @param EntityRepository<PaymentTransferCollection> $transferRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     */
    private function reconciler(EntityRepository $transferRepository, EntityRepository $stateRepository): PaymentTransferReconciler
    {
        $providers = new PaymentProviderRegistry([]);

        $configResolver = new PaymentConfigResolver(
            $providers,
            new PaymentChannelRegistry($providers),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher(self::SECRET),
        );

        $recipients = static::createStub(EntityRepository::class);
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $transferService = new PaymentTransferService(
            $transferRepository,
            $recipients,
            new PaymentTransferStateHandler(static::createStub(StateMachineRegistry::class)),
            new PaymentTransferRecipientService($recipients, new PaymentCredentialCipher(self::SECRET), new MockClock('2026-10-18T09:00:00+00:00')),
            new PaymentCredentialCipher(self::SECRET),
            $initialStateIdLoader,
            new MockClock('2026-10-18T09:00:00+00:00'),
        );

        return new PaymentTransferReconciler(
            $configResolver,
            $transferService,
            $transferRepository,
            $stateRepository,
            new MockClock('2026-10-18T09:00:00+00:00'),
            static::createStub(LoggerInterface::class),
        );
    }

    private function state(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId('state-' . $technicalName);
        $state->setTechnicalName($technicalName);

        return $state;
    }
}
