<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationTranslation\OrganizationTranslationCollection;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitCollection;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected bool $active;

    protected ?\DateTimeInterface $createdAt = null;

    protected ?\DateTimeInterface $updatedAt = null;

    protected ?OrganizationUnitCollection $units = null;

    protected ?OrganizationTranslationCollection $translations = null;

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

    public function getUnits(): ?OrganizationUnitCollection
    {
        return $this->units;
    }

    public function setUnits(OrganizationUnitCollection $units): void
    {
        $this->units = $units;
    }

    public function getTranslations(): ?OrganizationTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrganizationTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
