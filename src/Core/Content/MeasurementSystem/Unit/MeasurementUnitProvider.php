<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MeasurementSystem\Unit;

use Shopwell\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopwell\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\Service\ResetInterface;

#[Package('inventory')]
class MeasurementUnitProvider extends AbstractMeasurementUnitProvider implements ResetInterface
{
    /**
     * @var EntityCollection<MeasurementDisplayUnitEntity>|null
     */
    private ?EntityCollection $units = null;

    /**
     * @param EntityRepository<EntityCollection<MeasurementDisplayUnitEntity>> $measurementDisplayUnitRepository
     *
     * @internal
     */
    public function __construct(private readonly EntityRepository $measurementDisplayUnitRepository)
    {
    }

    public function getUnitInfo(string $unit): MeasurementDisplayUnitEntity
    {
        $units = $this->getUnits();

        $availableUnits = $units->map(static function (MeasurementDisplayUnitEntity $unit) {
            return $unit->shortName;
        });

        $foundUnit = $units->firstWhere(static function (MeasurementDisplayUnitEntity $unitEntity) use ($unit) {
            return $unitEntity->shortName === $unit;
        });

        if (!$foundUnit instanceof MeasurementDisplayUnitEntity) {
            throw MeasurementSystemException::unsupportedMeasurementUnit($unit, $availableUnits);
        }

        return $foundUnit;
    }

    public function reset(): void
    {
        $this->units = null;
    }

    public function getDecorated(): AbstractMeasurementUnitProvider
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return EntityCollection<MeasurementDisplayUnitEntity>
     */
    private function getUnits(): EntityCollection
    {
        if ($this->units !== null) {
            return $this->units;
        }

        return $this->units = $this->measurementDisplayUnitRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
    }
}
