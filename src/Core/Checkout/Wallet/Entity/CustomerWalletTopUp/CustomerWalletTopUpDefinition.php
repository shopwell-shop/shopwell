<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
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

/**
 * A top-up a customer pays online.
 *
 * The row is the intent, the payment order of the gateway is the attempt: a customer that closes the
 * payment interface and tries again gets a new top-up, while a top-up that was paid is credited once -
 * the state of the row tells whether it was. Keeping the intent apart from the payment attempt is what
 * makes a redelivered payment notification harmless.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWalletTopUpDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'customer_wallet_top_up';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerWalletTopUpCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerWalletTopUpEntity::class;
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

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('currency', 'currency', 3))->addFlags(new ApiAware(), new Required()),
            (new FloatField('amount', 'amount'))->addFlags(new ApiAware(), new Required()),

            (new StringField('state', 'state', 32))->addFlags(new ApiAware(), new Required()),

            (new StringField('payment_no', 'paymentNo', 64))->addFlags(new ApiAware()),
            (new StringField('provider_technical_name', 'providerTechnicalName', 64))->addFlags(new ApiAware()),
            (new StringField('channel_technical_name', 'channelTechnicalName', 64))->addFlags(new ApiAware()),

            (new DateTimeField('paid_at', 'paidAt'))->addFlags(new ApiAware()),
            (new DateTimeField('credited_at', 'creditedAt'))->addFlags(new ApiAware()),

            // the administration lists a top-up together with the customer it belongs to and searches by
            // the customer name, and `ApiCriteriaValidator` refuses every association the target is not
            // api aware for - without the flag each of those requests answers 400 instead of data.
            // The flag is an exposure marker only: it changes no column and no migration.
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false))
                ->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
