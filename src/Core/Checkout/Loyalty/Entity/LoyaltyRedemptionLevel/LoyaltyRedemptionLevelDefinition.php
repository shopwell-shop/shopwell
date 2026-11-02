<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * The steps of the redemption ladder, ordered by the point count they start at.
 *
 * A row is a step, not a range: `(points: 1000, valuePerPoint: 0.012)` means "from 1000 points on,
 * a point is worth 1.2 cents", and it stays in force until the next row begins. Below the first row
 * the shop wide rate from `core.loyalty.pointValueInCurrency` applies. Because every row only marks a
 * start, two rows can never overlap and a missing second bound cannot be forgotten - the number of
 * states that can go wrong is zero.
 *
 * The rows are read as bands by {@see \Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRedemptionCalculator}:
 * a customer who redeems 5000 points gets the first band at the base rate and only the points above a
 * threshold at the better one. The alternative - switching the rate for the whole amount as soon as a
 * threshold is reached - was rejected: 4999 points worth 50.00 next to 5000 points worth 75.00 is a
 * cliff a customer notices and complains about, and no merchant can explain it at the counter.
 *
 * No associations and no `active` flag: a step nobody wants any more is deleted, and nothing points at
 * a step from the past. What a redemption was worth is recomputable from the order, which stores the
 * points and the amount next to each other.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyRedemptionLevelDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'loyalty_redemption_level';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LoyaltyRedemptionLevelCollection::class;
    }

    public function getEntityClass(): string
    {
        return LoyaltyRedemptionLevelEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new IntField('points', 'points'))->addFlags(new ApiAware(), new Required()),
            (new FloatField('value_per_point', 'valuePerPoint'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
