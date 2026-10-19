<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
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
use Shopwell\Core\System\Currency\CurrencyDefinition;

/**
 * A membership a customer bought.
 *
 * Tier, duration and price are copied from the plan when the order is written: a plan that is edited
 * or removed afterwards must not change what a customer already paid for, and the row has to stay
 * readable when the plan is gone. The state tells whether the membership runs on the customer - the
 * activation happens exactly once, keyed on this row.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipOrderDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'membership_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MembershipOrderCollection::class;
    }

    public function getEntityClass(): string
    {
        return MembershipOrderEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new FkField('membership_plan_id', 'membershipPlanId', MembershipPlanDefinition::class))->addFlags(new ApiAware()),
            (new FkField('loyalty_tier_id', 'loyaltyTierId', LoyaltyTierDefinition::class))->addFlags(new ApiAware()),

            (new StringField('plan_name', 'planName'))->addFlags(new ApiAware()),
            (new FloatField('amount', 'amount'))->addFlags(new ApiAware(), new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('currency', 'currency', 3))->addFlags(new ApiAware(), new Required()),
            (new IntField('duration_days', 'durationDays'))->addFlags(new ApiAware(), new Required()),

            (new StringField('state', 'state', 32))->addFlags(new ApiAware(), new Required()),
            (new StringField('payment_no', 'paymentNo', 64))->addFlags(new ApiAware()),
            (new StringField('channel_technical_name', 'channelTechnicalName', 64))->addFlags(new ApiAware()),

            (new DateTimeField('starts_at', 'startsAt'))->addFlags(new ApiAware()),
            (new DateTimeField('expires_at', 'expiresAt'))->addFlags(new ApiAware()),
            (new DateTimeField('paid_at', 'paidAt'))->addFlags(new ApiAware()),
            (new DateTimeField('activated_at', 'activatedAt'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            new ManyToOneAssociationField('membershipPlan', 'membership_plan_id', MembershipPlanDefinition::class, 'id', false),
            new ManyToOneAssociationField('loyaltyTier', 'loyalty_tier_id', LoyaltyTierDefinition::class, 'id', false),
        ]);
    }
}
