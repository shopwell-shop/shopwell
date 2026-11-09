<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Diagnostics;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum ViolationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
