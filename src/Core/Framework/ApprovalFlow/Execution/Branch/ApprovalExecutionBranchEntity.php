<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Branch;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Scope\ApprovalExecutionScopeEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalExecutionBranchEntity extends Entity
{
    use EntityIdTrait;

    protected string $scopeId;

    protected string $connectionId;

    protected string $connectionHash;

    protected ?string $arrivalConnectionId = null;

    protected ApprovalExecutionBranchStatus $status;

    protected ?ApprovalExecutionScopeEntity $scope = null;

    public function getScopeId(): string
    {
        return $this->scopeId;
    }

    public function setScopeId(string $scopeId): void
    {
        $this->scopeId = $scopeId;
    }

    public function getConnectionId(): string
    {
        return $this->connectionId;
    }

    public function setConnectionId(string $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    public function getArrivalConnectionId(): ?string
    {
        return $this->arrivalConnectionId;
    }

    public function setArrivalConnectionId(?string $arrivalConnectionId): void
    {
        $this->arrivalConnectionId = $arrivalConnectionId;
    }

    public function getStatus(): ApprovalExecutionBranchStatus
    {
        return $this->status;
    }

    public function setStatus(ApprovalExecutionBranchStatus $status): void
    {
        $this->status = $status;
    }

    public function getScope(): ?ApprovalExecutionScopeEntity
    {
        return $this->scope;
    }

    public function setScope(?ApprovalExecutionScopeEntity $scope): void
    {
        $this->scope = $scope;
    }

    public function getConnectionHash(): string
    {
        return $this->connectionHash;
    }

    public function setConnectionHash(string $connectionHash): void
    {
        $this->connectionHash = $connectionHash;
    }
}
