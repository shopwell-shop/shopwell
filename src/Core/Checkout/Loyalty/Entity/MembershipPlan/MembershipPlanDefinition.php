<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder\MembershipOrderDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation\MembershipPlanTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyDefinition;

/**
 * What a customer can buy: a tier, for a number of days, at a price.
 *
 * The plan is the offer and never the purchase - a purchase copies tier, duration and price onto the
 * order, so changing a plan later does not rewrite what a customer already bought.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipPlanDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'membership_plan';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MembershipPlanCollection::class;
    }

    public function getEntityClass(): string
    {
        return MembershipPlanEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new StringField('technical_name', 'technicalName', 64))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            (new FkField('loyalty_tier_id', 'loyaltyTierId', LoyaltyTierDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new FloatField('price', 'price'))->addFlags(new ApiAware(), new Required()),
            (new IntField('duration_days', 'durationDays'))->addFlags(new ApiAware(), new Required()),

            (new BoolField('active', 'active'))->addFlags(new ApiAware()),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('description'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('loyaltyTier', 'loyalty_tier_id', LoyaltyTierDefinition::class, 'id', false),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false),
            new OneToManyAssociationField('orders', MembershipOrderDefinition::class, 'membership_plan_id', 'id'),

            (new TranslationsAssociationField(MembershipPlanTranslationDefinition::class, 'membership_plan_id'))->addFlags(new Required()),
        ]);
    }
}
