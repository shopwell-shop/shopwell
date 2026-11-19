<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalTaskEntity extends Entity
{
    use EntityIdTrait;

    protected string $executionId;

    protected string $assigneeId;

    protected ApprovalTaskStatus $status;

    protected ?ApprovalNodeExecutionEntity $execution = null;

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function setExecutionId(string $executionId): void
    {
        $this->executionId = $executionId;
    }

    public function getAssigneeId(): string
    {
        return $this->assigneeId;
    }

    public function setAssigneeId(string $assigneeId): void
    {
        $this->assigneeId = $assigneeId;
    }

    public function getStatus(): ApprovalTaskStatus
    {
        return $this->status;
    }

    public function setStatus(ApprovalTaskStatus $status): void
    {
        $this->status = $status;
    }

    public function getExecution(): ?ApprovalNodeExecutionEntity
    {
        return $this->execution;
    }

    public function setExecution(?ApprovalNodeExecutionEntity $execution): void
    {
        $this->execution = $execution;
    }
}
