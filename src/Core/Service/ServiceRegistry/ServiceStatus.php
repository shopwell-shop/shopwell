<?php declare(strict_types=1);

namespace Shopwell\Core\Service\ServiceRegistry;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
enum ServiceStatus: string
{
    case ACTIVE = 'active';
    case RETIRED = 'retired';
}
