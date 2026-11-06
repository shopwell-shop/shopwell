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
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTaskHandler;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Tests\Unit\Core\System\Payment\Order\_fixtures\PaymentOrderReconcilerFixture;

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
            new StateMachineStateCollection([PaymentOrderReconcilerFixture::openedState()]),
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
            PaymentOrderReconcilerFixture::reconciler($orderRepository, $stateRepository)
        );

        $handler->run();
    }
}
