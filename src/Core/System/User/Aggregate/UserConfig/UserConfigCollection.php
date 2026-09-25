<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserConfig;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UserConfigEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserConfigEntity::class;
    }
}
