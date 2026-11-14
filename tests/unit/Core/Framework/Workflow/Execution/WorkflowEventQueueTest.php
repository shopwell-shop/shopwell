<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowEventQueue::class)]
class WorkflowEventQueueTest extends TestCase
{
    public function testNestedFlushWaitsForTheOwningOperationToFinish(): void
    {
        $dispatcher = new EventDispatcher();
        $events = new WorkflowEventQueue($dispatcher);
        $observed = [];
        $dispatcher->addListener(\stdClass::class, static function (object $event) use (&$observed): void {
            $observed[] = $event;
        });
        $event = new \stdClass();

        $result = $events->defer(function () use ($events, $event, &$observed): object {
            $events->push($event);
            $events->flush();
            static::assertSame([], $observed);

            return $event;
        });

        static::assertSame($event, $result);
        static::assertSame([$event], $observed);
        static::assertTrue($events->isEmpty());
    }

    public function testFailureDiscardsOnlyTheFailedOperationsEvents(): void
    {
        $events = new WorkflowEventQueue(new EventDispatcher());
        $prior = new \stdClass();
        $events->push($prior);
        $failure = new \RuntimeException('Binding failed');
        $this->expectExceptionObject($failure);

        try {
            $events->defer(function () use ($events, $failure): void {
                $events->push(new \stdClass());
                $events->flush();

                throw $failure;
            });
        } finally {
            static::assertSame([$prior], $events->pending());
        }
    }
}
