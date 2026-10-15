<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
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
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerPointTransactionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'customer_point_transaction';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerPointTransactionCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerPointTransactionEntity::class;
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
            (new StringField('type', 'type'))->addFlags(new ApiAware()),
            (new IntField('points', 'points'))->addFlags(new ApiAware()),
            (new IntField('balance_after', 'balanceAfter'))->addFlags(new ApiAware()),
            (new StringField('idempotency_key', 'idempotencyKey'))->addFlags(new ApiAware()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),
            (new DateTimeField('expires_at', 'expiresAt'))->addFlags(new ApiAware()),
            (new DateTimeField('expired_at', 'expiredAt'))->addFlags(new ApiAware()),
            (new StringField('comment', 'comment'))->addFlags(new ApiAware()),
            (new FkField('administrator_id', 'administratorId', UserDefinition::class))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
