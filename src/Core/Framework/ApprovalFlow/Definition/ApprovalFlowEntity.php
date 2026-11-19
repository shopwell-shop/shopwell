<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Definition;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalFlowEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected ?string $createdById = null;

    protected ?UserEntity $createdBy = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(?string $createdById): void
    {
        $this->createdById = $createdById;
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
