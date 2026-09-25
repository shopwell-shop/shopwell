<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order;

use Shopwell\Core\Checkout\Document\DocumentDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\NoConstraint;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyDefinition;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopwell\Core\System\Tag\TagDefinition;
use Shopwell\Core\System\User\UserDefinition;

#[Package('checkout')]
class OrderDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $billingAddressAssociation = Feature::isActive('v6.8.0.0')
            ? new OneToOneAssociationField('billingAddress', 'billing_address_id', 'id', OrderAddressDefinition::class, false)
            : new ManyToOneAssociationField('billingAddress', 'billing_address_id', OrderAddressDefinition::class);
        $billingAddressAssociation->addFlags(new ApiAware())->setDescription('Billing address for the order');

        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of order.'),
            (new VersionField())->addFlags(new ApiAware()),

            new AutoIncrementField(),

            (new NumberRangeField('order_number', 'orderNumber'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING, false))->setDescription('Unique number associated with every order.'),

            (new FkField('billing_address_id', 'billingAddressId', OrderAddressDefinition::class))->addFlags(new ApiAware(), new Required(), new NoConstraint())->setDescription('Unique identity of the billing address.'),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'billing_address_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('primary_order_delivery_id', 'primaryOrderDeliveryId', OrderDeliveryDefinition::class))->addFlags(new ApiAware(), new NoConstraint()),
            (new ReferenceVersionField(OrderDeliveryDefinition::class, 'primary_order_delivery_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('primary_order_transaction_id', 'primaryOrderTransactionId', OrderTransactionDefinition::class))->addFlags(new ApiAware(), new NoConstraint()),
            (new ReferenceVersionField(OrderTransactionDefinition::class, 'primary_order_transaction_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the currency.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the language.'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the sales channel.'),

            (new DateTimeField('order_date_time', 'orderDateTime'))->addFlags(new ApiAware(), new Required())->setDescription('Timestamp when the order was placed.'),
            (new DateField('order_date', 'orderDate'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('Date when the order was placed.'),
            (new CartPriceField('price', 'price'))->addFlags(new ApiAware())->setDescription('TaxStatus takes `Free`, `Net` or `Gross` as values.'),
            (new FloatField('amount_total', 'amountTotal'))->addFlags(new ApiAware(), new WriteProtected(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Gross price of the order.'),
            (new FloatField('amount_net', 'amountNet'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('Net price of the order.'),
            (new FloatField('position_price', 'positionPrice'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('Price of each line item in the cart multiplied by its quantity excluding charges like shipping cost, rules, taxes etc.'),
            (new StringField('tax_status', 'taxStatus'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('TaxStatus takes `Free`, `Net` or `Gross` as values.'),
            (new CalculatedPriceField('shipping_costs', 'shippingCosts'))->addFlags(new ApiAware())->setDescription('Contains cheapest price from last 30 days as per EU law.'),
            (new FloatField('shipping_total', 'shippingTotal'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('Total shipping cost of the ordered product.'),
            (new FloatField('currency_factor', 'currencyFactor'))->addFlags(new ApiAware(), new Required())->setDescription('Rate at which currency is exchanged.'),
            (new StringField('deep_link_code', 'deepLinkCode'))->addFlags(new ApiAware())->setDescription('It is a generated special code linked to email. It is used to access orders placed by guest customers.'),
            (new StringField('affiliate_code', 'affiliateCode'))->addFlags(new ApiAware())->setDescription('An affiliate code is an identification option with which website operators can mark outgoing links.'),
            (new StringField('campaign_code', 'campaignCode'))->addFlags(new ApiAware())->setDescription('A campaign code is the globally unique identifier for a campaign.'),
            (new LongTextField('customer_comment', 'customerComment'))->addFlags(new ApiAware(), new AllowEmptyString())->setDescription('Comments given by comments.'),
            (new LongTextField('internal_comment', 'internalComment'))->addFlags(new AllowEmptyString()),
            (new StringField('source', 'source'))->addFlags(new ApiAware())->setDescription('Source of orders either via normal order placement or subscriptions.'),
            (new StringField('tax_calculation_type', 'taxCalculationType'))->addFlags(new ApiAware()),

            (new StateMachineStateField('state_id', 'stateId', OrderStates::STATE_MACHINE))->addFlags(new Required())->setDescription('Unique identity of state.'),
            (new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, 'id'))->addFlags(new ApiAware())->setDescription('Current order state (e.g., open, in_progress, completed, cancelled)'),
            (new ListField('rule_ids', 'ruleIds', StringField::class))->setDescription('Unique identity of rule.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new CreatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]))->addFlags(new ApiAware())->setDescription('Unique identity of createdBy.'),
            (new UpdatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]))->addFlags(new ApiAware())->setDescription('Unique identity of updatedBy.'),

            (new OneToOneAssociationField('primaryOrderDelivery', 'primary_order_delivery_id', 'id', OrderDeliveryDefinition::class, false))->addFlags(new ApiAware())->setDescription('Primary delivery information for the order'),
            (new OneToOneAssociationField('primaryOrderTransaction', 'primary_order_transaction_id', 'id', OrderTransactionDefinition::class, false))->addFlags(new ApiAware())->setDescription('Primary payment transaction for the order'),
            (new OneToOneAssociationField('orderCustomer', 'id', 'order_id', OrderCustomerDefinition::class))->addFlags(new ApiAware(), new CascadeDelete(), new SearchRanking(0.5))->setDescription('Customer information associated with the order'),
            (new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Currency used for the order'),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Language used when placing the order'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            (new OneToManyAssociationField('addresses', OrderAddressDefinition::class, 'order_id'))->addFlags(new ApiAware(), new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('All addresses associated with the order (billing and shipping)'),
            $billingAddressAssociation,
            (new OneToManyAssociationField('deliveries', OrderDeliveryDefinition::class, 'order_id'))->addFlags(new ApiAware(), new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Delivery information including shipping address and tracking'),
            (new OneToManyAssociationField('lineItems', OrderLineItemDefinition::class, 'order_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Order line items (products, discounts, fees)'),
            (new OneToManyAssociationField('transactions', OrderTransactionDefinition::class, 'order_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Payment transactions for the order'),
            (new OneToManyAssociationField('documents', DocumentDefinition::class, 'order_id'))->addFlags(new ApiAware())->setDescription('Generated documents (invoices, delivery notes, credit notes)'),
            (new ManyToManyAssociationField('tags', TagDefinition::class, OrderTagDefinition::class, 'order_id', 'tag_id'))->addFlags(new ApiAware(), new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Tags assigned to the order for organization and filtering'),
            new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('updatedBy', 'updated_by_id', UserDefinition::class, 'id', false),
            (new CashRoundingConfigField('item_rounding', 'itemRounding'))->addFlags(new Required())->setDescription('The cash rounding applied on net prices.'),
            (new CashRoundingConfigField('total_rounding', 'totalRounding'))->addFlags(new Required())->setDescription('The cash rounding applied on net prices.'),
        ]);
    }
}
