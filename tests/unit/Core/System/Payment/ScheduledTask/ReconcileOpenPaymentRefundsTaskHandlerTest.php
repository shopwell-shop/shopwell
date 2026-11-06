<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentRefundReconciler;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentRefundsTaskHandler;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReconcileOpenPaymentRefundsTaskHandler::class)]
class ReconcileOpenPaymentRefundsTaskHandlerTest extends TestCase
{
    public function testScheduledExecutionLooksForUnsettledRefunds(): void
    {
        $states = StaticEntityRepository::of(StateMachineStateCollection::class, [[]]);
        $refunds = StaticEntityRepository::of(PaymentRefundCollection::class);
        $providers = PaymentControllerFixture::providerRegistry();
        $config = PaymentControllerFixture::configResolver($providers, PaymentControllerFixture::channelRegistry($providers));
        $orders = StaticEntityRepository::of(PaymentOrderCollection::class);
        $service = PaymentControllerFixture::refundService($refunds, $config, PaymentControllerFixture::orderService($orders));
        $handler = new ReconcileOpenPaymentRefundsTaskHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class),
            new NullLogger(),
            new PaymentRefundReconciler($refunds, $states, $service, PaymentControllerFixture::clock(), new NullLogger()),
        );

        $handler->run();

        static::assertCount(1, $states->searchedCriteria);
        static::assertSame([], $refunds->searchedCriteria);
    }
}
