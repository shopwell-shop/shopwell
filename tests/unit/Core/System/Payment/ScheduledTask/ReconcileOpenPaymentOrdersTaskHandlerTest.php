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
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReconcileOpenPaymentOrdersTaskHandler::class)]
class ReconcileOpenPaymentOrdersTaskHandlerTest extends TestCase
{
    public function testRunAsksTheInterfacesAboutTheDueOrders(): void
    {
        // the reconcile is what the handler runs, so its first read is the proof the handler ran it
        $stateRepository = static::createMock(EntityRepository::class);
        $stateRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult(
            'state_machine_state',
            1,
            new StateMachineStateCollection([$this->openedState()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            0,
            new PaymentOrderCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $handler = new ReconcileOpenPaymentOrdersTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->reconciler($orderRepository, $stateRepository)
        );

        $handler->run();
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     */
    private function reconciler(EntityRepository $orderRepository, EntityRepository $stateRepository): PaymentOrderReconciler
    {
        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn(static::createStub(\Payum\Core\GatewayInterface::class));

        $channel = new PaymentChannel('alipay_h5', 'alipay', 'Alipay H5', PaymentFlow::REDIRECT);

        $provider = new PaymentProvider(
            'alipay',
            'Alipay',
            [],
            ['alipay_h5' => $channel],
            $gatewayFactory,
            static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $configResolver = new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher('test-secret')
        );

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $orderService = new PaymentOrderService(
            $orderRepository,
            $configResolver,
            $initialStateIdLoader,
            new PaymentOrderStateHandler(static::createStub(StateMachineRegistry::class)),
            new MockClock('2024-01-01T00:00:00+00:00')
        );

        $webhookService = new PaymentWebhookDispatcher(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentWebhookSigner(new PaymentSignatureService(), new PaymentCredentialCipher('test-secret'), new MockClock('2024-01-01T00:00:00+00:00')),
            static::createStub(HttpClientInterface::class),
            new MockClock('2024-01-01T00:00:00+00:00'),
            static::createStub(LoggerInterface::class)
        );

        return new PaymentOrderReconciler(
            $orderRepository,
            $stateRepository,
            $configResolver,
            $orderService,
            $webhookService,
            static::createStub(EventDispatcherInterface::class),
            new MockClock('2024-01-01T00:00:00+00:00'),
            static::createStub(LoggerInterface::class)
        );
    }

    private function openedState(): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier('state-opened');
        $state->id = 'state-opened';

        return $state;
    }
}
