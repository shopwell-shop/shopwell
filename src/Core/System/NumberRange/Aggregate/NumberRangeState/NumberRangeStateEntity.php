<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange\Aggregate\NumberRangeState;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\NumberRange\NumberRangeEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NumberRangeStateEntity extends Entity
{
    use EntityIdTrait;

    protected string $numberRangeId;

    protected int $lastValue;

    protected ?NumberRangeEntity $numberRange = null;

    public function getNumberRangeId(): string
    {
        return $this->numberRangeId;
    }

    public function setNumberRangeId(string $numberRangeId): void
    {
        $this->numberRangeId = $numberRangeId;
    }

    public function getLastValue(): int
    {
        return $this->lastValue;
    }

    public function setLastValue(int $lastValue): void
    {
        $this->lastValue = $lastValue;
    }

    public function getNumberRange(): ?NumberRangeEntity
    {
        return $this->numberRange;
    }

    public function setNumberRange(?NumberRangeEntity $numberRange): void
    {
        $this->numberRange = $numberRange;
    }
}
