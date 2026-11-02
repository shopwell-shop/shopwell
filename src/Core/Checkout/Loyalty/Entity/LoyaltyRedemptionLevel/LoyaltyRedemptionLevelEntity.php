<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * One step of the redemption ladder: from `points` on, every point is worth `valuePerPoint`.
 *
 * The row does not describe a range - a step is valid until the next step begins, which is what makes
 * a gap or an overlap impossible by construction. See {@see LoyaltyRedemptionLevelDefinition} for how
 * the rows are read.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyRedemptionLevelEntity extends Entity
{
    use EntityIdTrait;

    protected int $points;

    protected float $valuePerPoint;

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getValuePerPoint(): float
    {
        return $this->valuePerPoint;
    }

    public function setValuePerPoint(float $valuePerPoint): void
    {
        $this->valuePerPoint = $valuePerPoint;
    }
}
