<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Release;

use Shopwell\Core\Framework\ApprovalFlow\Definition\ApprovalFlowEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalReleaseEntity extends Entity
{
    use EntityIdTrait;

    protected string $definitionId;

    protected int $draftRevision;

    protected string $graphJson;

    protected string $contractJson;

    protected string $graphPlanJson;

    protected string $nodePlanJson;

    protected string $dependenciesJson;

    protected string $contentHash;

    protected ?string $createdById = null;

    protected ?ApprovalFlowEntity $definition = null;

    protected ?UserEntity $createdBy = null;

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function setDefinitionId(string $definitionId): void
    {
        $this->definitionId = $definitionId;
    }

    public function getDraftRevision(): int
    {
        return $this->draftRevision;
    }

    public function setDraftRevision(int $draftRevision): void
    {
        $this->draftRevision = $draftRevision;
    }

    public function getGraphJson(): string
    {
        return $this->graphJson;
    }

    public function setGraphJson(string $graphJson): void
    {
        $this->graphJson = $graphJson;
    }

    public function getContractJson(): string
    {
        return $this->contractJson;
    }

    public function setContractJson(string $contractJson): void
    {
        $this->contractJson = $contractJson;
    }

    public function getGraphPlanJson(): string
    {
        return $this->graphPlanJson;
    }

    public function setGraphPlanJson(string $graphPlanJson): void
    {
        $this->graphPlanJson = $graphPlanJson;
    }

    public function getNodePlanJson(): string
    {
        return $this->nodePlanJson;
    }

    public function setNodePlanJson(string $nodePlanJson): void
    {
        $this->nodePlanJson = $nodePlanJson;
    }

    public function getDependenciesJson(): string
    {
        return $this->dependenciesJson;
    }

    public function setDependenciesJson(string $dependenciesJson): void
    {
        $this->dependenciesJson = $dependenciesJson;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function setContentHash(string $contentHash): void
    {
        $this->contentHash = $contentHash;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(?string $createdById): void
    {
        $this->createdById = $createdById;
    }

    public function getDefinition(): ?ApprovalFlowEntity
    {
        return $this->definition;
    }

    public function setDefinition(?ApprovalFlowEntity $definition): void
    {
        $this->definition = $definition;
    }

    public function getCreatedBy(): ?UserEntity
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserEntity $createdBy): void
    {
        $this->createdBy = $createdBy;
    }
}
