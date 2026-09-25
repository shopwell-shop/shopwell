<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MeasurementSystem\Unit;

use Shopwell\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractMeasurementUnitProvider
{
    abstract public function getDecorated(): AbstractMeasurementUnitProvider;

    abstract public function getUnitInfo(string $unit): MeasurementDisplayUnitEntity;
}
