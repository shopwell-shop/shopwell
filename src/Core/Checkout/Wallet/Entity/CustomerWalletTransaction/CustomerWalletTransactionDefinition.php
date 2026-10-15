<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction;

use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyDefinition;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWalletTransactionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'customer_wallet_transaction';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerWalletTransactionCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerWalletTransactionEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('customer_wallet_id', 'customerWalletId', CustomerWalletDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new StringField('type', 'type'))->addFlags(new ApiAware()),
            (new FloatField('amount', 'amount'))->addFlags(new ApiAware()),
            (new FloatField('balance_after', 'balanceAfter'))->addFlags(new ApiAware()),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware()),
            (new StringField('external_reference', 'externalReference'))->addFlags(new ApiAware()),
            (new StringField('comment', 'comment'))->addFlags(new ApiAware()),
            (new FkField('administrator_id', 'administratorId', UserDefinition::class))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('wallet', 'customer_wallet_id', CustomerWalletDefinition::class, 'id', false),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false),
        ]);
    }
}
