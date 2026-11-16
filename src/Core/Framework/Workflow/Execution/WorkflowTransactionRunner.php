<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Run retryable transactions with instance row locks; queue external effects because callbacks may be retried.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowTransactionRunner
{
    private int $activeOperations = 0;

    public function __construct(
        private readonly Connection $connection,
        private readonly WorkflowEventQueue $events,
    ) {
    }

    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $closure
     *
     * @return TReturn
     */
    public function run(\Closure $closure): mixed
    {
        $checkpoint = $this->events->checkpoint();
        ++$this->activeOperations;

        try {
            return RetryableTransaction::retryable($this->connection, function () use ($closure, $checkpoint): mixed {
                // A retry also follows commit failures, after the previous callback returned.
                $this->events->discardSince($checkpoint);

                return $closure();
            });
        } catch (\Throwable $exception) {
            $this->events->discardSince($checkpoint);

            throw $exception;
        } finally {
            --$this->activeOperations;
        }
    }

    public function isRunning(): bool
    {
        return $this->activeOperations > 0;
    }

    /**
     * Lock the instance until the active transaction ends; subsequent queries report missing instances.
     */
    public function lockInstance(string $instanceId): void
    {
        $this->connection->fetchOne(
            'SELECT `id` FROM `workflow_instance` WHERE `id` = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($instanceId)]
        );
    }
}
