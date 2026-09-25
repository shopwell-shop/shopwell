<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MeasurementSystem\Unit;

use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractMeasurementUnitConverter
{
    abstract public function getDecorated(): AbstractMeasurementUnitConverter;

    abstract public function convert(float $value, string $fromUnit, string $toUnit, ?int $precision = null): ConvertedUnit;
}
