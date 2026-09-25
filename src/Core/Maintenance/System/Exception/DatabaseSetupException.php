<?php declare(strict_types=1);

namespace Shopwell\Core\Maintenance\System\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Maintenance\MaintenanceException;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class DatabaseSetupException extends MaintenanceException
{
}
