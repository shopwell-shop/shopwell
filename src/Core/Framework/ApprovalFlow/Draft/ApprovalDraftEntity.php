<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Draft;

use Shopwell\Core\Framework\ApprovalFlow\Definition\ApprovalFlowEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalDraftEntity extends Entity
{
    protected string $definitionId;

    protected int $revision;

    protected string $graphJson;

    protected ?string $updatedById = null;

    protected ?ApprovalFlowEntity $definition = null;

    protected ?UserEntity $updatedBy = null;

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function setDefinitionId(string $definitionId): void
    {
        $this->definitionId = $definitionId;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function setRevision(int $revision): void
    {
        $this->revision = $revision;
    }

    public function getGraphJson(): string
    {
        return $this->graphJson;
    }

    public function setGraphJson(string $graphJson): void
    {
        $this->graphJson = $graphJson;
    }

    public function getUpdatedById(): ?string
    {
        return $this->updatedById;
    }

    public function setUpdatedById(?string $updatedById): void
    {
        $this->updatedById = $updatedById;
    }

    public function getDefinition(): ?ApprovalFlowEntity
    {
        return $this->definition;
    }

    public function setDefinition(?ApprovalFlowEntity $definition): void
    {
        $this->definition = $definition;
    }

    public function getUpdatedBy(): ?UserEntity
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?UserEntity $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }
}
