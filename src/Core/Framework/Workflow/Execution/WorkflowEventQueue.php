<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * 事务内收集事件，事务提交后再派发。
 *
 * 没有这一层时最典型的故障是：审批通过 → 发短信通知发起人 → 数据库提交失败回滚。
 * 用户收到"已通过"的短信，单子却还在待办里。
 *
 * 用法固定为：
 *
 * ```php
 * $this->transactions->run(fn () => $this->engine->completeTask(...));   // 只写库，事件进队列
 * $this->events->flush();                                                // 提交后才 dispatch
 * ```
 *
 * 队列是**请求级**的：`flush()` 之后清空。业务事务必须通过 `defer()` 延迟派发，
 * 才能在同一事务里调用 `WorkflowService` 并保存业务关联。
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
     * 派发并清空。事件名用类上的 `EVENT_NAME` 常量：
     * 监听器按同一个常量订阅，改名时编译期就能发现两处不一致。
     */
    public function flush(): void
    {
        if ($this->deferrals > 0) {
            return;
        }

        // 先取走再清空：监听器里如果又 push 了事件（比如"通过后自动发起下一单"），
        // 不应该在本次 flush 里被无限派发下去
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
     * 只在测试里用：断言"没有残留事件"。
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
