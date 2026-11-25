<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Scope;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalExecutionScopeEntity extends Entity
{
    use EntityIdTrait;

    protected string $instanceId;

    protected ?string $parentId = null;

    protected int $generation = 1;

    protected string $joinNodeId;

    protected ApprovalExecutionScopeStatus $status;

    protected ?ApprovalInstanceEntity $instance = null;

    protected ?self $parent = null;

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function setInstanceId(string $instanceId): void
    {
        $this->instanceId = $instanceId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function setGeneration(int $generation): void
    {
        $this->generation = $generation;
    }

    public function getJoinNodeId(): string
    {
        return $this->joinNodeId;
    }

    public function setJoinNodeId(string $joinNodeId): void
    {
        $this->joinNodeId = $joinNodeId;
    }

    public function getStatus(): ApprovalExecutionScopeStatus
    {
        return $this->status;
    }

    public function setStatus(ApprovalExecutionScopeStatus $status): void
    {
        $this->status = $status;
    }

    public function getInstance(): ?ApprovalInstanceEntity
    {
        return $this->instance;
    }

    public function setInstance(?ApprovalInstanceEntity $instance): void
    {
        $this->instance = $instance;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }
}
