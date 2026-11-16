<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Queue request-scoped events until commit; defer dispatch when WorkflowService shares a business transaction.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowEventQueue
{
    /**
     * @var list<object>
     */
    private array $events = [];

    private int $deferrals = 0;

    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function push(object $event): void
    {
        $this->events[] = $event;
    }

    public function checkpoint(): int
    {
        return \count($this->events);
    }

    public function discardSince(int $checkpoint): void
    {
        $this->events = \array_slice($this->events, 0, $checkpoint);
    }

    /**
     * Let a business transaction commit its binding before any nested facade flush can dispatch.
     *
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function defer(\Closure $operation): mixed
    {
        $checkpoint = $this->checkpoint();
        ++$this->deferrals;

        try {
            $result = $operation();
        } catch (\Throwable $exception) {
            $this->discardSince($checkpoint);

            throw $exception;
        } finally {
            --$this->deferrals;
        }

        // Dispatch failures happen after the transaction committed and must never replay writes.
        $this->flush();

        return $result;
    }

    /**
     * Dispatch and clear queued events using each event's EVENT_NAME constant.
     */
    public function flush(): void
    {
        if ($this->deferrals > 0) {
            return;
        }

        // Clear the batch before dispatch so events added by listeners wait for the next flush.
        $events = $this->events;
        $this->events = [];

        foreach ($events as $event) {
            $name = $this->eventName($event);
            $this->dispatcher->dispatch($event, $name);
        }
    }

    public function isEmpty(): bool
    {
        return $this->events === [];
    }

    /**
     * Expose queued events for test assertions.
     *
     * @return list<object>
     */
    public function pending(): array
    {
        return $this->events;
    }

    private function eventName(object $event): ?string
    {
        $constant = $event::class . '::EVENT_NAME';

        if (!\defined($constant)) {
            return null;
        }

        /** @var string $name */
        $name = \constant($constant);

        return $name;
    }
}
