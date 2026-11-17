<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntime;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeException;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatus;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\ApprovalWorkflowRuntimeTest
 */
#[Package('framework')]
final class ApprovalWorkflowRuntime implements WorkflowRuntime
{
    private bool $ownsTransaction = false;

    public function __construct(
        private readonly ApprovalWorkflowService $flows,
        private readonly WorkflowTransactionRunner $transactions,
        private readonly WorkflowEventQueue $events,
        private readonly Connection $connection,
        private readonly WorkflowService $workflows,
    ) {
    }

    public function name(): string
    {
        return ApprovalWorkflowPublisher::NAME;
    }

    public function transactional(\Closure $operation): mixed
    {
        if ($this->connection->getTransactionNestingLevel() !== 0) {
            throw WorkflowRuntimeException::enclosingTransaction();
        }

        return $this->events->defer(function () use ($operation): mixed {
            $this->ownsTransaction = true;
            try {
                return $this->transactions->run($operation);
            } finally {
                $this->ownsTransaction = false;
            }
        });
    }

    public function start(WorkflowReleaseEntity $release, array $data, string $title, Context $context): string
    {
        $this->assertTransactionOwnership();
        $this->assertRelease($release);

        return $this->workflows->start($release->getId(), $data, $title, $context);
    }

    public function status(WorkflowReleaseEntity $release, string $instanceId, Context $context): WorkflowRuntimeStatus
    {
        $instance = $this->instance($release, $instanceId, $context);
        $status = match ($instance->state()) {
            WorkflowInstanceStates::RUNNING => WorkflowRuntimeStatus::PENDING,
            WorkflowInstanceStates::RETURNED => WorkflowRuntimeStatus::RETURNED,
            WorkflowInstanceStates::AGREED => WorkflowRuntimeStatus::APPROVED,
            WorkflowInstanceStates::REJECTED => WorkflowRuntimeStatus::REJECTED,
            WorkflowInstanceStates::CANCELED => WorkflowRuntimeStatus::CANCELED,
            default => throw WorkflowRuntimeException::invalidReference(),
        };
        $source = $context->getSource();
        $actorId = $source instanceof AdminApiSource ? $source->getUserId() : null;
        $detailRoute = null;
        if ($actorId !== null && $context->isAllowed('workflow_instance:access')) {
            try {
                $this->flows->instanceDetail($instanceId, $context, $actorId);
                $detailRoute = ['name' => 'sw.workflow.instance', 'params' => ['id' => $instanceId]];
            } catch (WorkflowExecutionException $exception) {
                if ($exception->getErrorCode() !== WorkflowExecutionException::FORBIDDEN) {
                    throw $exception;
                }
            }
        }

        return new WorkflowRuntimeStatus(
            $status,
            $detailRoute,
            \in_array($status, [WorkflowRuntimeStatus::PENDING, WorkflowRuntimeStatus::RETURNED], true) && $actorId === $instance->initiatorId,
        );
    }

    public function cancel(WorkflowReleaseEntity $release, string $instanceId, string $reason, Context $context): void
    {
        $this->assertTransactionOwnership();
        $this->instance($release, $instanceId, $context);
        $this->flows->cancel($instanceId, $reason, $context);
    }

    private function instance(WorkflowReleaseEntity $release, string $instanceId, Context $context): WorkflowInstanceEntity
    {
        $this->assertRelease($release);
        $instance = $this->workflows->loadInstance($instanceId, $context);
        if ($instance->definitionId !== $release->getId()) {
            throw WorkflowRuntimeException::invalidReference();
        }

        return $instance;
    }

    private function assertRelease(WorkflowReleaseEntity $release): void
    {
        if ($release->getPublisher() !== $this->name()) {
            throw WorkflowRuntimeException::invalidReference();
        }
    }

    private function assertTransactionOwnership(): void
    {
        if ($this->connection->getTransactionNestingLevel() !== 0 && !$this->ownsTransaction) {
            throw WorkflowRuntimeException::enclosingTransaction();
        }
    }
}
