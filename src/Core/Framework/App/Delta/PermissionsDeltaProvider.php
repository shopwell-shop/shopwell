<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Delta;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Privileges\Utils;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Struct\PermissionCollection;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PermissionsDeltaProvider extends AbstractAppDeltaProvider
{
    final public const DELTA_NAME = 'permissions';

    public function getDeltaName(): string
    {
        return self::DELTA_NAME;
    }

    /**
     * @return array<string, PermissionCollection>
     */
    public function getReport(Manifest $manifest, AppEntity $app): array
    {
        $permissions = $manifest->getPermissions();

        if (!$permissions) {
            return [];
        }

        return Utils::makeCategorizedPermissions($permissions->asParsedPrivileges());
    }

    public function hasDelta(Manifest $manifest, AppEntity $app): bool
    {
        $permissions = $manifest->getPermissions();

        if (!$permissions) {
            return false;
        }

        $aclRole = $app->getAclRole();

        if (!$aclRole) {
            return true;
        }

        $newPrivileges = $permissions->asParsedPrivileges();
        $currentPrivileges = $aclRole->getPrivileges();

        $privilegesDelta = array_diff($newPrivileges, $currentPrivileges);

        return $privilegesDelta !== [];
    }
}
