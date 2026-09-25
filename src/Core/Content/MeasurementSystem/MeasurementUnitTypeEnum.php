<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MeasurementSystem;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
enum MeasurementUnitTypeEnum: string
{
    case LENGTH = 'length';
    case WEIGHT = 'weight';
}
