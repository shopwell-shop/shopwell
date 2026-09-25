<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Sorting;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSortingTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'product_sorting_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductSortingTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductSortingTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.2.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ProductSortingDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new ApiAware(), new Required()),
        ]);

        return $collection;
    }
}
