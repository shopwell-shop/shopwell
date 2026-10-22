<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserPosition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UserPositionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserPositionCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(static fn (UserPositionEntity $userPosition) => $userPosition->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(static fn (UserPositionEntity $userPosition) => $userPosition->getUserId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'user_position_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserPositionEntity::class;
    }
}
