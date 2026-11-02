<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierDefinition;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderDefinition;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * The membership a customer holds right now, and the ones they held before.
 *
 * One row is one uninterrupted period: a renewal of the same tier pushes `ends_at` of the running row,
 * while buying another tier ends it and opens a new one. That is what keeps "since when is this
 * customer a member" answerable - an order only says what was bought, and the loyalty account only
 * says what the tier is today.
 *
 * The tier, the plan and the price are copies on purpose, exactly like on the order: a plan that is
 * renamed or repriced, or a tier that is deleted, must not rewrite the membership a customer paid for.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipSubscriptionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'membership_subscription';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MembershipSubscriptionCollection::class;
    }

    public function getEntityClass(): string
    {
        return MembershipSubscriptionEntity::class;
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

            (new FkField('membership_plan_id', 'membershipPlanId', MembershipPlanDefinition::class))->addFlags(new ApiAware(), new SetNullOnDelete()),
            (new FkField('loyalty_tier_id', 'loyaltyTierId', LoyaltyTierDefinition::class))->addFlags(new ApiAware(), new SetNullOnDelete()),

            (new StringField('plan_name', 'planName'))->addFlags(new ApiAware()),
            (new FloatField('amount', 'amount'))->addFlags(new ApiAware(), new Required()),
            (new StringField('currency', 'currency', 3))->addFlags(new ApiAware()),

            (new StringField('state', 'state', 32))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('starts_at', 'startsAt'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('ends_at', 'endsAt'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('ended_at', 'endedAt'))->addFlags(new ApiAware()),
            (new StringField('end_reason', 'endReason', 32))->addFlags(new ApiAware()),

            (new FkField('last_order_id', 'lastOrderId', MembershipOrderDefinition::class))->addFlags(new ApiAware(), new SetNullOnDelete()),

            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            new ManyToOneAssociationField('membershipPlan', 'membership_plan_id', MembershipPlanDefinition::class, 'id', false),
            new ManyToOneAssociationField('loyaltyTier', 'loyalty_tier_id', LoyaltyTierDefinition::class, 'id', false),
            new ManyToOneAssociationField('lastOrder', 'last_order_id', MembershipOrderDefinition::class, 'id', false),
        ]);
    }
}
