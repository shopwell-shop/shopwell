<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Node;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Branch\ApprovalExecutionBranchEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Scope\ApprovalExecutionScopeEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskCollection;
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

    protected ?string $approvalPolicy = null;

    protected ?string $approvalPolicyVersion = null;

    protected ?ApprovalOutcome $outcome = null;

    protected ?ApprovalInstanceEntity $instance = null;

    protected ?ApprovalTaskCollection $tasks = null;

    protected ?string $scopeId = null;

    protected ?string $branchId = null;

    protected ?string $openedScopeId = null;

    protected ?string $waitingScopeId = null;

    protected int $generation = 1;

    protected bool $advanced = false;

    protected ?ApprovalExecutionScopeEntity $scope = null;

    protected ?ApprovalExecutionBranchEntity $branch = null;

    protected ?ApprovalExecutionScopeEntity $openedScope = null;

    protected ?ApprovalExecutionScopeEntity $waitingScope = null;

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

    public function getApprovalPolicy(): ?string
    {
        return $this->approvalPolicy;
    }

    public function setApprovalPolicy(?string $approvalPolicy): void
    {
        $this->approvalPolicy = $approvalPolicy;
    }

    public function getApprovalPolicyVersion(): ?string
    {
        return $this->approvalPolicyVersion;
    }

    public function setApprovalPolicyVersion(?string $approvalPolicyVersion): void
    {
        $this->approvalPolicyVersion = $approvalPolicyVersion;
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

    public function getTasks(): ?ApprovalTaskCollection
    {
        return $this->tasks;
    }

    public function setTasks(?ApprovalTaskCollection $tasks): void
    {
        $this->tasks = $tasks;
    }

    public function getScopeId(): ?string
    {
        return $this->scopeId;
    }

    public function setScopeId(?string $scopeId): void
    {
        $this->scopeId = $scopeId;
    }

    public function getBranchId(): ?string
    {
        return $this->branchId;
    }

    public function setBranchId(?string $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function getOpenedScopeId(): ?string
    {
        return $this->openedScopeId;
    }

    public function setOpenedScopeId(?string $openedScopeId): void
    {
        $this->openedScopeId = $openedScopeId;
    }

    public function getWaitingScopeId(): ?string
    {
        return $this->waitingScopeId;
    }

    public function setWaitingScopeId(?string $waitingScopeId): void
    {
        $this->waitingScopeId = $waitingScopeId;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function setGeneration(int $generation): void
    {
        $this->generation = $generation;
    }

    public function getAdvanced(): bool
    {
        return $this->advanced;
    }

    public function setAdvanced(bool $advanced): void
    {
        $this->advanced = $advanced;
    }

    public function getScope(): ?ApprovalExecutionScopeEntity
    {
        return $this->scope;
    }

    public function setScope(?ApprovalExecutionScopeEntity $scope): void
    {
        $this->scope = $scope;
    }

    public function getBranch(): ?ApprovalExecutionBranchEntity
    {
        return $this->branch;
    }

    public function setBranch(?ApprovalExecutionBranchEntity $branch): void
    {
        $this->branch = $branch;
    }

    public function getOpenedScope(): ?ApprovalExecutionScopeEntity
    {
        return $this->openedScope;
    }

    public function setOpenedScope(?ApprovalExecutionScopeEntity $openedScope): void
    {
        $this->openedScope = $openedScope;
    }

    public function getWaitingScope(): ?ApprovalExecutionScopeEntity
    {
        return $this->waitingScope;
    }

    public function setWaitingScope(?ApprovalExecutionScopeEntity $waitingScope): void
    {
        $this->waitingScope = $waitingScope;
    }
}
