<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Mapping;

use Shopwell\Core\Framework\Log\Package;

/**
 * An unavailable mapping or incompatible runtime value cannot use authored fallback.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum MappingRejection
{
    case Unavailable;
    case Incompatible;
}
