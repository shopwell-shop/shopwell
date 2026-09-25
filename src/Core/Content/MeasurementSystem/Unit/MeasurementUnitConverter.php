<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MeasurementSystem\Unit;

use Shopwell\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('inventory')]
class MeasurementUnitConverter extends AbstractMeasurementUnitConverter
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractMeasurementUnitProvider $unitProvider)
    {
    }

    public function convert(float $value, string $fromUnit, string $toUnit, ?int $precision = null): ConvertedUnit
    {
        if ($fromUnit === $toUnit) {
            return new ConvertedUnit($value, $toUnit);
        }

        $fromUnitInfo = $this->unitProvider->getUnitInfo($fromUnit);
        $toUnitInfo = $this->unitProvider->getUnitInfo($toUnit);

        if ($fromUnitInfo->type !== $toUnitInfo->type) {
            throw MeasurementSystemException::incompatibleMeasurementUnits($fromUnit, $toUnit);
        }

        if ($toUnitInfo->factor === 0.0) {
            throw MeasurementSystemException::measurementUnitCantHaveZeroFactor($toUnit);
        }

        $value = $value * $fromUnitInfo->factor / $toUnitInfo->factor;

        // Use the target unit's precision from database if no override is provided
        $targetRounding = $precision ?? $toUnitInfo->precision;
        $roundedValue = round($value, $targetRounding);

        return new ConvertedUnit($roundedValue, $toUnit);
    }

    public function getDecorated(): AbstractMeasurementUnitConverter
    {
        throw new DecorationPatternException(self::class);
    }
}
