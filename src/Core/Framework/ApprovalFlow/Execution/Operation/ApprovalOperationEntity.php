<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Operation;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalOperationEntity extends Entity
{
    protected string $instanceId;

    protected string $commandId;

    protected string $operation;

    protected string $requestHash;

    protected string $actorJson;

    protected string $reason;

    protected ApprovalInstanceStatus $previousStatus;

    protected ApprovalInstanceStatus $status;

    protected ?ApprovalOutcome $outcome = null;

    protected int $previousVersion;

    protected int $aggregateVersion;

    protected ?ApprovalInstanceEntity $instance = null;

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function setInstanceId(string $instanceId): void
    {
        $this->instanceId = $instanceId;
    }

    public function getCommandId(): string
    {
        return $this->commandId;
    }

    public function setCommandId(string $commandId): void
    {
        $this->commandId = $commandId;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): void
    {
        $this->operation = $operation;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function setRequestHash(string $requestHash): void
    {
        $this->requestHash = $requestHash;
    }

    public function getActorJson(): string
    {
        return $this->actorJson;
    }

    public function setActorJson(string $actorJson): void
    {
        $this->actorJson = $actorJson;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function getPreviousStatus(): ApprovalInstanceStatus
    {
        return $this->previousStatus;
    }

    public function setPreviousStatus(ApprovalInstanceStatus $previousStatus): void
    {
        $this->previousStatus = $previousStatus;
    }

    public function getStatus(): ApprovalInstanceStatus
    {
        return $this->status;
    }

    public function setStatus(ApprovalInstanceStatus $status): void
    {
        $this->status = $status;
    }

    public function getOutcome(): ?ApprovalOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(?ApprovalOutcome $outcome): void
    {
        $this->outcome = $outcome;
    }

    public function getPreviousVersion(): int
    {
        return $this->previousVersion;
    }

    public function setPreviousVersion(int $previousVersion): void
    {
        $this->previousVersion = $previousVersion;
    }

    public function getAggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    public function setAggregateVersion(int $aggregateVersion): void
    {
        $this->aggregateVersion = $aggregateVersion;
    }

    public function getInstance(): ?ApprovalInstanceEntity
    {
        return $this->instance;
    }

    public function setInstance(?ApprovalInstanceEntity $instance): void
    {
        $this->instance = $instance;
    }
}
