<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopwell\Core\Service\Permission\ConsentState;
use Shopwell\Core\Service\Permission\PermissionsConsent;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class LogPermissionToRegistryMessage implements AsyncMessageInterface
{
    public function __construct(public readonly PermissionsConsent $permissionsConsent, public readonly ConsentState $consentState)
    {
    }
}
