<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class AdminMenuItemEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $parentId = null;

    protected ?string $afterMenuItemId = null;

    protected ?string $internalKey = null;

    protected ?string $title = null;

    protected ?string $icon = null;

    protected ?string $color = null;

    protected string $type;

    protected ?string $linkTarget = null;

    protected ?string $privilege = null;

    protected bool $active;

    protected bool $sysLocked;

    protected ?string $pluginId = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $defaultMeta = null;

    protected bool $configOverridden;

    protected bool $archived;

    protected ?\DateTimeInterface $createdAt = null;

    protected ?\DateTimeInterface $updatedAt = null;

    protected ?AdminMenuItemEntity $parent = null;

    protected ?AdminMenuItemCollection $children = null;

    protected ?PluginEntity $plugin = null;

    protected ?AclRoleCollection $roles = null;

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getAfterMenuItemId(): ?string
    {
        return $this->afterMenuItemId;
    }

    public function setAfterMenuItemId(?string $afterMenuItemId): void
    {
        $this->afterMenuItemId = $afterMenuItemId;
    }

    public function getInternalKey(): ?string
    {
        return $this->internalKey;
    }

    public function setInternalKey(?string $internalKey): void
    {
        $this->internalKey = $internalKey;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getLinkTarget(): ?string
    {
        return $this->linkTarget;
    }

    public function setLinkTarget(?string $linkTarget): void
    {
        $this->linkTarget = $linkTarget;
    }

    public function getPrivilege(): ?string
    {
        return $this->privilege;
    }

    public function setPrivilege(?string $privilege): void
    {
        $this->privilege = $privilege;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isSysLocked(): bool
    {
        return $this->sysLocked;
    }

    public function setSysLocked(bool $sysLocked): void
    {
        $this->sysLocked = $sysLocked;
    }

    public function getPluginId(): ?string
    {
        return $this->pluginId;
    }

    public function setPluginId(?string $pluginId): void
    {
        $this->pluginId = $pluginId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDefaultMeta(): ?array
    {
        return $this->defaultMeta;
    }

    /**
     * @param array<string, mixed>|null $defaultMeta
     */
    public function setDefaultMeta(?array $defaultMeta): void
    {
        $this->defaultMeta = $defaultMeta;
    }

    public function isConfigOverridden(): bool
    {
        return $this->configOverridden;
    }

    public function setConfigOverridden(bool $configOverridden): void
    {
        $this->configOverridden = $configOverridden;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): void
    {
        $this->archived = $archived;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getParent(): ?AdminMenuItemEntity
    {
        return $this->parent;
    }

    public function setParent(?AdminMenuItemEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?AdminMenuItemCollection
    {
        return $this->children;
    }

    public function setChildren(AdminMenuItemCollection $children): void
    {
        $this->children = $children;
    }

    public function getPlugin(): ?PluginEntity
    {
        return $this->plugin;
    }

    public function setPlugin(?PluginEntity $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getRoles(): ?AclRoleCollection
    {
        return $this->roles;
    }

    public function setRoles(AclRoleCollection $roles): void
    {
        $this->roles = $roles;
    }
}
