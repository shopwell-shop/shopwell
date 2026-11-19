<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Node;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceEntity;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalPolicy;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalNodeExecutionEntity extends Entity
{
    use EntityIdTrait;

    protected string $instanceId;

    protected string $nodeId;

    protected ?string $incomingConnectionId = null;

    protected int $sequence;

    protected ApprovalNodeExecutionStatus $status;

    protected ?ApprovalPolicy $approvalPolicy = null;

    protected ?ApprovalOutcome $outcome = null;

    protected ?ApprovalInstanceEntity $instance = null;

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function setInstanceId(string $instanceId): void
    {
        $this->instanceId = $instanceId;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function setNodeId(string $nodeId): void
    {
        $this->nodeId = $nodeId;
    }

    public function getIncomingConnectionId(): ?string
    {
        return $this->incomingConnectionId;
    }

    public function setIncomingConnectionId(?string $incomingConnectionId): void
    {
        $this->incomingConnectionId = $incomingConnectionId;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): void
    {
        $this->sequence = $sequence;
    }

    public function getStatus(): ApprovalNodeExecutionStatus
    {
        return $this->status;
    }

    public function setStatus(ApprovalNodeExecutionStatus $status): void
    {
        $this->status = $status;
    }

    public function getInstance(): ?ApprovalInstanceEntity
    {
        return $this->instance;
    }

    public function getApprovalPolicy(): ?ApprovalPolicy
    {
        return $this->approvalPolicy;
    }

    public function setApprovalPolicy(?ApprovalPolicy $approvalPolicy): void
    {
        $this->approvalPolicy = $approvalPolicy;
    }

    public function getOutcome(): ?ApprovalOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(?ApprovalOutcome $outcome): void
    {
        $this->outcome = $outcome;
    }

    public function setInstance(?ApprovalInstanceEntity $instance): void
    {
        $this->instance = $instance;
    }
}
