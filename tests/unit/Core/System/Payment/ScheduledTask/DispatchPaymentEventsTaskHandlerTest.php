<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\System\Payment\Order\Event\PaymentEventDispatcher;
use Shopwell\Core\System\Payment\ScheduledTask\DispatchPaymentEventsTaskHandler;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DispatchPaymentEventsTaskHandler::class)]
class DispatchPaymentEventsTaskHandlerTest extends TestCase
{
    public function testScheduledRunRetriesPendingEvents(): void
    {
        $dispatcher = static::createMock(PaymentEventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatchPending')->willReturn(2);

        (new DispatchPaymentEventsTaskHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class),
            new NullLogger(),
            $dispatcher,
        ))->run();
    }
}
