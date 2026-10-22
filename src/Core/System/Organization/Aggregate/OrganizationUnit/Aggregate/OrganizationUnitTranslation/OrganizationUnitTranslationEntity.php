<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\Aggregate\OrganizationUnitTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationUnitTranslationEntity extends TranslationEntity
{
    protected string $organizationUnitId;

    protected ?string $name = null;

    protected ?OrganizationUnitEntity $organizationUnit = null;

    public function getOrganizationUnitId(): string
    {
        return $this->organizationUnitId;
    }

    public function setOrganizationUnitId(string $organizationUnitId): void
    {
        $this->organizationUnitId = $organizationUnitId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
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
