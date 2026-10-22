<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\OrganizationEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationTranslationEntity extends TranslationEntity
{
    protected string $organizationId;

    protected ?string $name = null;

    protected ?OrganizationEntity $organization = null;

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function setOrganizationId(string $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getOrganization(): ?OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(?OrganizationEntity $organization): void
    {
        $this->organization = $organization;
    }
}
