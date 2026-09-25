<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductFeatureSetTranslation;

use Shopwell\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
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
class ProductFeatureSetTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = ProductFeatureSetDefinition::ENTITY_NAME . '_translation';

    public function getCollectionClass(): string
    {
        return ProductFeatureSetTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductFeatureSetTranslationEntity::class;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ProductFeatureSetDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('description', 'description'))->addFlags(new ApiAware()),
        ]);
    }
}
