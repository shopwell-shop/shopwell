<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * One thing a tier grants, as a row instead of a column.
 *
 * The three columns this replaces were a closed list: a fourth benefit meant a migration, a form field
 * and a change in every consumer. Here the row says which rule applies (`type`), how much of it
 * (`value`) and where it is shown (`position`); see {@see \Shopwell\Core\Checkout\Loyalty\LoyaltyBenefitTypes}
 * for what each type makes of its value.
 *
 * The pair (tier, type) is unique. Two `earn_multiplier` rows would leave every reader with the same
 * unanswerable question, so the schema refuses the state instead of tolerating it.
 *
 * Deleting a tier deletes its benefits - a benefit has no meaning without the tier that grants it and
 * nothing points back at one from the past. The order stores what an order was worth, not the rules
 * that were in force.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyTierBenefitDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'loyalty_tier_benefit';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LoyaltyTierBenefitCollection::class;
    }

    public function getEntityClass(): string
    {
        return LoyaltyTierBenefitEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('loyalty_tier_id', 'loyaltyTierId', LoyaltyTierDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('type', 'type', 32))->addFlags(new ApiAware(), new Required()),
            (new FloatField('value', 'value'))->addFlags(new ApiAware(), new Required()),
            (new IntField('position', 'position'))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('loyaltyTier', 'loyalty_tier_id', LoyaltyTierDefinition::class, 'id', false),
        ]);
    }
}
