<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Update\Services;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum VersionHistoryEntryType: string
{
    case INSTALL = 'install';
    case UPDATE = 'update';
}
