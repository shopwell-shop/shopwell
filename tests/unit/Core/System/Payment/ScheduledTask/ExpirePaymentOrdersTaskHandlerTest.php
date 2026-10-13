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
use Shopwell\Core\System\Payment\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\Service\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Service\PaymentOrderService;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExpirePaymentOrdersTaskHandler::class)]
class ExpirePaymentOrdersTaskHandlerTest extends TestCase
{
    public function testRunExpiresTheDueOrders(): void
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier('order-id');
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = 'state-created';

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            1,
            new PaymentOrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $orderRepository->expects($this->once())->method('update');

        $handler = new ExpirePaymentOrdersTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->orderService($orderRepository)
        );

        $handler->run();
    }

    public function testRunDoesNothingWhenNoOrderIsDue(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            0,
            new PaymentOrderCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $orderRepository->expects($this->never())->method('update');

        $handler = new ExpirePaymentOrdersTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->orderService($orderRepository)
        );

        $handler->run();
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     */
    private function orderService(EntityRepository $orderRepository): PaymentOrderService
    {
        $providerRegistry = new PaymentProviderRegistry([]);

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

        return new PaymentOrderService(
            $orderRepository,
            $configResolver,
            $initialStateIdLoader,
            static::createStub(StateMachineRegistry::class),
            new MockClock('2024-01-01T00:00:00+00:00')
        );
    }
}
