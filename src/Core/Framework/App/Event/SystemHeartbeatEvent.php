<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Event;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopwell\Core\Framework\Webhook\Hookable;

/**
 * @internal
 */
#[Package('framework')]
class SystemHeartbeatEvent implements Hookable
{
    final public const NAME = 'app.system_heartbeat';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{}
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
