<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Permission;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum ConsentState: string
{
    case GRANTED = 'granted';
    case REVOKED = 'revoked';
}
