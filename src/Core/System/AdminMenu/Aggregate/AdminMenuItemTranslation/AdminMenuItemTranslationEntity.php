<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class AdminMenuItemTranslationEntity extends TranslationEntity
{
    protected string $adminMenuItemId;

    protected ?string $title = null;

    protected ?string $groupTitle = null;

    protected ?AdminMenuItemEntity $adminMenuItem = null;

    public function getAdminMenuItemId(): string
    {
        return $this->adminMenuItemId;
    }

    public function setAdminMenuItemId(string $adminMenuItemId): void
    {
        $this->adminMenuItemId = $adminMenuItemId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getGroupTitle(): ?string
    {
        return $this->groupTitle;
    }

    public function setGroupTitle(?string $groupTitle): void
    {
        $this->groupTitle = $groupTitle;
    }

    public function getAdminMenuItem(): ?AdminMenuItemEntity
    {
        return $this->adminMenuItem;
    }

    public function setAdminMenuItem(?AdminMenuItemEntity $adminMenuItem): void
    {
        $this->adminMenuItem = $adminMenuItem;
    }
}
