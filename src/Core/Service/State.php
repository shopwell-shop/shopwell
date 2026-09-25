<?php declare(strict_types=1);

namespace Shopwell\Core\Service;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum State: string
{
    case ACTIVE = 'active';

    case PENDING_PERMISSIONS = 'pending_permissions';

    case INACTIVE = 'inactive';

    public static function state(AppEntity $appEntity): State
    {
        if ($appEntity->getRequestedPrivileges() === [] && $appEntity->isActive()) {
            return State::ACTIVE;
        }

        if ($appEntity->getRequestedPrivileges() !== [] && $appEntity->isActive()) {
            return State::PENDING_PERMISSIONS;
        }

        return State::INACTIVE;
    }
}
