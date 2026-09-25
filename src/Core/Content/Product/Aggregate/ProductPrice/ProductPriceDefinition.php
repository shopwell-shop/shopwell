<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductPrice;

use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Rule\RuleDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductPriceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_price';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductPriceCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductPriceEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductPriceHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the product\'s price.'),
            new VersionField(),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the rule.'),
            (new PriceField('price', 'price'))->addFlags(new Required())->setDescription('Price of the Product.'),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false))->addFlags(new ReverseInherited('prices')),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', false),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);

        // @deprecated tag:v6.8.0 - add min value for quantity_start and quantity_end
        if (Feature::isActive('v6.8.0.0')) {
            $fields->add((new IntField('quantity_start', 'quantityStart', 1))->addFlags(new Required())->setDescription('Starting range of quantity of an item.'));
            $fields->add((new IntField('quantity_end', 'quantityEnd', 1))->setDescription('Ending range of quantity of an item.'));
        } else {
            $fields->add((new IntField('quantity_start', 'quantityStart'))->addFlags(new Required())->setDescription('Starting range of quantity of an item.'));
            $fields->add((new IntField('quantity_end', 'quantityEnd'))->setDescription('Ending range of quantity of an item.'));
        }

        return $fields;
    }
}
