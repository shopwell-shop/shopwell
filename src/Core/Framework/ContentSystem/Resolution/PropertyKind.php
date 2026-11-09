<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Resolution;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum PropertyKind: string
{
    case Primitive = 'primitive';
    case Reference = 'reference';
}
