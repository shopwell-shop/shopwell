<?php declare(strict_types=1);

namespace Shopwell\Core\System\DeliveryTime;

use Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class DeliveryTimeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'delivery_time';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return DeliveryTimeEntity::class;
    }

    public function getCollectionClass(): string
    {
        return DeliveryTimeCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of delivery time.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('min', 'min', 0))->addFlags(new ApiAware(), new Required())->setDescription('Minimum delivery time taken.'),
            (new IntField('max', 'max', 0))->addFlags(new ApiAware(), new Required())->setDescription('Maximum delivery time taken.'),
            (new StringField('unit', 'unit'))->addFlags(new ApiAware(), new Required())->setDescription('Unit in which the delivery time is defined. For example, days or hours.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'delivery_time_id'),
            new OneToManyAssociationField('products', ProductDefinition::class, 'delivery_time_id'),
            (new TranslationsAssociationField(DeliveryTimeTranslationDefinition::class, 'delivery_time_id'))->addFlags(new Required()),
        ]);
    }
}
