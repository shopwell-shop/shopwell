<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationUnit;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\Aggregate\OrganizationUnitTranslation\OrganizationUnitTranslationCollection;
use Shopwell\Core\System\Organization\OrganizationEntity;
use Shopwell\Core\System\User\UserCollection;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationUnitEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $organizationId;

    protected ?string $parentId = null;

    protected ?int $level = null;

    protected ?string $path = null;

    protected ?int $childCount = null;

    protected ?string $name = null;

    protected bool $active;

    protected ?OrganizationUnitEntity $parent = null;

    protected ?OrganizationUnitCollection $children = null;

    protected ?OrganizationEntity $organization = null;

    protected ?OrganizationUnitTranslationCollection $translations = null;

    protected ?UserCollection $users = null;

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function setOrganizationId(string $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(?int $level): void
    {
        $this->level = $level;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getChildCount(): ?int
    {
        return $this->childCount;
    }

    public function setChildCount(?int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getParent(): ?OrganizationUnitEntity
    {
        return $this->parent;
    }

    public function setParent(?OrganizationUnitEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?OrganizationUnitCollection
    {
        return $this->children;
    }

    public function setChildren(OrganizationUnitCollection $children): void
    {
        $this->children = $children;
    }

    public function getOrganization(): ?OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(OrganizationEntity $organization): void
    {
        $this->organization = $organization;
    }

    public function getTranslations(): ?OrganizationUnitTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrganizationUnitTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getUsers(): ?UserCollection
    {
        return $this->users;
    }

    public function setUsers(UserCollection $users): void
    {
        $this->users = $users;
    }
}
