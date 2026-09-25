<?php declare(strict_types=1);

namespace Shopwell\Core\Maintenance\SalesChannel\Command;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal should be used over the CLI only
 */
#[Package('discovery')]
#[AsCommand(
    name: 'sales-channel:maintenance:disable',
    description: 'Disable maintenance mode for a sales channel',
)]
class SalesChannelMaintenanceDisableCommand extends SalesChannelMaintenanceEnableCommand
{
    protected bool $setMaintenanceMode = false;
}
