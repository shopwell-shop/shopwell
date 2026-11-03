<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionDefinition;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot\LoyaltyPointLotDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * Which lot one booking took points out of, and how many.
 *
 * The lot keeps only how many points are left, which is enough to spend and to expire but not to
 * answer "where did those 500 points come from". A refund needs exactly that: it has to put the
 * points back into the lot they left, keeping the expiry date the customer originally received.
 * Refunding into a fresh lot would extend that date, which is a gift nobody decided to give.
 *
 * The row is also the paper trail for support: a booking that spans three lots writes three rows, so
 * the sum of a transaction's uses equals the points it booked.
 *
 * `returned_points` counts how much of the withdrawal a return already gave back. An order can come
 * back in parts, so "how much of this withdrawal is still out" has to be stored per row - a total per
 * order would not say which lot owes the points.
 *
 * Both references delete with their parent - a use has no meaning without the booking it belongs to.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyPointLotUseDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'loyalty_point_lot_use';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LoyaltyPointLotUseCollection::class;
    }

    public function getEntityClass(): string
    {
        return LoyaltyPointLotUseEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('lot_id', 'lotId', LoyaltyPointLotDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('transaction_id', 'transactionId', LoyaltyPointTransactionDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new IntField('points', 'points'))->addFlags(new ApiAware(), new Required()),
            (new IntField('returned_points', 'returnedPoints'))->addFlags(new ApiAware(), new Required()),
            new ManyToOneAssociationField('lot', 'lot_id', LoyaltyPointLotDefinition::class, 'id', false),
            new ManyToOneAssociationField('transaction', 'transaction_id', LoyaltyPointTransactionDefinition::class, 'id', false),
        ]);
    }
}
