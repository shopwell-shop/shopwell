<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Acl\Role;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AclRoleEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AclRoleCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_acl_role_collection';
    }

    protected function getExpectedClass(): string
    {
        return AclRoleEntity::class;
    }
}
