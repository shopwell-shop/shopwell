<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class OrderLineItemDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order_line_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderLineItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderLineItemEntity::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return ['position' => 1];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of OrderLineItem.'),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of order.'),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of product.'),
            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('cover_id', 'coverId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of cover image.'),
            (new ManyToOneAssociationField('cover', 'cover_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Line item image or thumbnail'),

            (new StringField('identifier', 'identifier'))->addFlags(new ApiAware(), new Required())->setDescription('It is a unique identity of an item in cart before its converted to an order.'),
            (new StringField('referenced_id', 'referencedId'))->addFlags(new ApiAware())->setDescription('Unique identity of type of entity.'),
            (new IntField('quantity', 'quantity'))->addFlags(new ApiAware(), new Required())->setDescription('Number of items of product.'),
            (new StringField('label', 'label'))->addFlags(new ApiAware(), new Required())->setDescription('It is a typical product name given to the line item.'),
            (new JsonField('payload', 'payload'))->addFlags(new ApiAware())->setDescription('Any data related to product is passed.'),
            (new BoolField('good', 'good'))->addFlags(new ApiAware())->setDescription('When set to true, it indicates the line item is physical else it is virtual.'),
            (new BoolField('removable', 'removable'))->addFlags(new ApiAware())->setDescription('Allows the line item to be removable from the cart when set to true.'),
            (new BoolField('stackable', 'stackable'))->addFlags(new ApiAware())->setDescription('Allows to change the quantity of the line item when set to true.'),
            (new IntField('position', 'position'))->addFlags(new ApiAware(), new Required())->setDescription('Position of line items placed in an order.'),

            (new CalculatedPriceField('price', 'price'))->addFlags(new Required())->setDescription('Contains cheapest price from last 30 days as per EU law.'),
            (new PriceDefinitionField('price_definition', 'priceDefinition'))->addFlags(new ApiAware())->setDescription('Description of how the price has to be calculated. For example, in percentage or absolute value, etc.'),

            (new FloatField('unit_price', 'unitPrice'))->addFlags(new ApiAware(), new Computed())->setDescription('Price of product per item (where, quantity=1).'),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new ApiAware(), new Computed())->setDescription('Cost of product based on quantity.'),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware())->setDescription('Description of line items in an order.'),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Choice([
                LineItem::PRODUCT_LINE_ITEM_TYPE,
                LineItem::CREDIT_LINE_ITEM_TYPE,
                LineItem::CUSTOM_LINE_ITEM_TYPE,
                LineItem::PROMOTION_LINE_ITEM_TYPE,
                LineItem::CONTAINER_LINE_ITEM,
                LineItem::DISCOUNT_LINE_ITEM,
                LineItem::QUANTITY_LINE_ITEM,
            ]))->setDescription('Type refers to the entity type of an item whether it is product or promotion for instance.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Referenced product if this is a product line item'),
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id', false),
            (new OneToManyAssociationField('orderDeliveryPositions', OrderDeliveryPositionDefinition::class, 'order_line_item_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete(), new WriteProtected())->setDescription('Delivery positions for this line item'),
            (new OneToManyAssociationField('orderTransactionCaptureRefundPositions', OrderTransactionCaptureRefundPositionDefinition::class, 'order_line_item_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('downloads', OrderLineItemDownloadDefinition::class, 'order_line_item_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Digital downloads associated with this line item'),
            (new ParentAssociationField(self::class))->addFlags(new ApiAware()),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware(), new Required()),
        ]);

        if (!Feature::isActive('v6.8.0.0')) {
            $fields->add(
                (new ListField('states', 'states', StringField::class))
                    ->addFlags(new ApiAware(), new Required(), new Deprecated('v6.7.6.0', 'v6.8.0.0', 'payload.productType'))->setDescription('Internal field.'),
            );
        }

        return $fields;
    }
}
