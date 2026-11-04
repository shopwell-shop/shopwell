<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserOrganizationUnit;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitEntity;
use Shopwell\Core\System\User\UserEntity;

/**
 * The row that attaches a user to an organization unit, and the only place where the
 * "primary unit" flag lives - comparable to DingTalk / WeCom primary department semantics.
 *
 * The row carries a surrogate id, because the administration reads and diffs the rows
 * directly, and the dal can only hydrate entities with a single primary key. The pair of
 * `user_id` and `organization_unit_id` stays unique.
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserOrganizationUnitEntity extends Entity
{
    protected string $userId;

    protected string $organizationUnitId;

    protected ?bool $isPrimary = null;

    protected ?UserEntity $user = null;

    protected ?OrganizationUnitEntity $organizationUnit = null;

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getOrganizationUnitId(): string
    {
        return $this->organizationUnitId;
    }

    public function setOrganizationUnitId(string $organizationUnitId): void
    {
        $this->organizationUnitId = $organizationUnitId;
    }

    public function getIsPrimary(): ?bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(?bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
    }

    public function getOrganizationUnit(): ?OrganizationUnitEntity
    {
        return $this->organizationUnit;
    }

    public function setOrganizationUnit(?OrganizationUnitEntity $organizationUnit): void
    {
        $this->organizationUnit = $organizationUnit;
    }
}
