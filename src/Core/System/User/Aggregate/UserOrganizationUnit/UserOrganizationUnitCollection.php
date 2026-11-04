<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserOrganizationUnit;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UserOrganizationUnitEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserOrganizationUnitCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getOrganizationUnitIds(): array
    {
        return $this->fmap(static fn (UserOrganizationUnitEntity $mapping) => $mapping->getOrganizationUnitId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(static fn (UserOrganizationUnitEntity $mapping) => $mapping->getUserId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'user_organization_unit_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserOrganizationUnitEntity::class;
    }
}
