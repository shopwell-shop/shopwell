<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserRecovery;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UserRecoveryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserRecoveryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_recovery_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserRecoveryEntity::class;
    }
}
