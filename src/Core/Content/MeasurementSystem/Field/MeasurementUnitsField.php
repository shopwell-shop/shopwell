<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MeasurementSystem\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class MeasurementUnitsField extends ObjectField
{
    protected function getSerializerClass(): string
    {
        return MeasurementUnitsFieldSerializer::class;
    }
}
