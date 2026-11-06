<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse\LoyaltyPointLotUseDefinition;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * One delivery of points and what is left of it.
 *
 * `points` never changes: the customer earned that many. `remaining_points` is the mutable part and
 * shrinks when points are spent or when the lot expires. `expires_at` belongs to the delivery, not to
 * the balance, which is why a balance alone could never say what dies next.
 *
 * `created_at` doubles as the arrival time: it is what the lot was booked at, and it breaks ties
 * between two lots that expire on the same day. A separate `awarded_at` would only repeat it.
 *
 * A null `expires_at` means the lot never expires - that is what a manual adjustment by an
 * administrator books, because nothing in the settings gave it a lifetime.
 *
 * The lots of one customer always add up to their `points_balance`; that is the invariant
 * {@see \Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointLots} maintains. Reading a balance from
 * here would be slower than the projection and no more correct, so the balance stays on the account.
 *
 * Nothing deletes a lot but the customer or the order it came from. A spent lot stays as a row with
 * zero remaining: "this customer once earned 500 points on that order" is history, and history that
 * disappears cannot be audited.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyPointLotDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'loyalty_point_lot';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LoyaltyPointLotCollection::class;
    }

    public function getEntityClass(): string
    {
        return LoyaltyPointLotEntity::class;
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
            (new IntField('points', 'points'))->addFlags(new ApiAware(), new Required()),
            (new IntField('remaining_points', 'remainingPoints'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('expires_at', 'expiresAt'))->addFlags(new ApiAware()),
            (new DateTimeField('expired_at', 'expiredAt'))->addFlags(new ApiAware()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new OneToManyAssociationField('uses', LoyaltyPointLotUseDefinition::class, 'lot_id'),
        ]);
    }
}
