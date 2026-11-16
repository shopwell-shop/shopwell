<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserPosition;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitEntity;
use Shopwell\Core\System\Position\PositionEntity;
use Shopwell\Core\System\User\UserEntity;

/**
 * A position held by a user within one of their departments.
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserPositionEntity extends Entity
{
    use EntityIdTrait;

    protected string $organizationUnitId;

    protected bool $isPrimary = false;

    protected ?OrganizationUnitEntity $organizationUnit = null;

    protected string $userId;

    protected string $positionId;

    protected ?UserEntity $user = null;

    protected ?PositionEntity $position = null;

    public function getOrganizationUnitId(): string
    {
        return $this->organizationUnitId;
    }

    public function setOrganizationUnitId(string $organizationUnitId): void
    {
        $this->organizationUnitId = $organizationUnitId;
    }

    public function getIsPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }

    public function getOrganizationUnit(): ?OrganizationUnitEntity
    {
        return $this->organizationUnit;
    }

    public function setOrganizationUnit(?OrganizationUnitEntity $organizationUnit): void
    {
        $this->organizationUnit = $organizationUnit;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getPositionId(): string
    {
        return $this->positionId;
    }

    public function setPositionId(string $positionId): void
    {
        $this->positionId = $positionId;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
    }

    public function getPosition(): ?PositionEntity
    {
        return $this->position;
    }

    public function setPosition(?PositionEntity $position): void
    {
        $this->position = $position;
    }
}
