<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('framework')]
final class ApprovalUserEligibility
{
    public static function isEligible(UserEntity $user): bool
    {
        if (!$user->getActive()) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        $privileges = [];
        foreach ($user->getAclRoles() ?? [] as $role) {
            $privileges = [...$privileges, ...$role->getPrivileges()];
        }

        return array_diff(['workflow_instance:access', 'workflow_task:decide'], $privileges) === [];
    }
}
