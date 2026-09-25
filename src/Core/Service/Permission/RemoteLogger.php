<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Permission;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface RemoteLogger
{
    public function log(PermissionsConsent $consent, ConsentState $state): void;
}
