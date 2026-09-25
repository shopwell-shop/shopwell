<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductSearchConfig;

use Shopwell\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSearchConfigDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_search_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductSearchConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductSearchConfigCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.5.0';
    }

    public function getDefaults(): array
    {
        return [
            'andLogic' => true,
            'minSearchLength' => 2,
            'excludedTerms' => [],
        ];
    }

    public function getHydratorClass(): string
    {
        return ProductSearchConfigHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Product Search Configuration.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required())->setDescription('Unique identity of language.'),
            (new BoolField('and_logic', 'andLogic'))->addFlags(new Required())->setDescription('Product search configuration with add logic.'),
            (new IntField('min_search_length', 'minSearchLength'))->addFlags(new Required())->setDescription('Minimum number of characters used for product search.'),
            (new ListField('excluded_terms', 'excludedTerms', StringField::class))->setDescription('Excluded terms in product search.'),
            new OneToOneAssociationField('language', 'language_id', 'id', LanguageDefinition::class, false),
            (new OneToManyAssociationField('configFields', ProductSearchConfigFieldDefinition::class, 'product_search_config_id', 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
