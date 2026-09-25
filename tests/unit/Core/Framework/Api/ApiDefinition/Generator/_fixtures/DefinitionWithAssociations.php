<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class DefinitionWithAssociations extends EntityDefinition
{
    final public const ENTITY_NAME = 'test_entity_with_associations';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),

            // Association with description and ApiAware for Store API
            (new ManyToOneAssociationField(
                'category',
                'category_id',
                SimpleDefinition::class,
                'id'
            ))->addFlags(new ApiAware(SalesChannelApiSource::class))->setDescription('The category this entity belongs to'),

            // Association without description but ApiAware
            (new OneToManyAssociationField(
                'children',
                SimpleDefinition::class,
                'parent_id'
            ))->addFlags(new ApiAware(SalesChannelApiSource::class)),

            // Association with IgnoreInOpenapiSchema flag
            (new ManyToManyAssociationField(
                'hiddenAssociation',
                SimpleDefinition::class,
                SimpleDefinition::class,
                'entity_id',
                'simple_id'
            ))->addFlags(
                new ApiAware(SalesChannelApiSource::class),
                new IgnoreInOpenapiSchema()
            ),

            // Translations association
            new TranslationsAssociationField(
                SimpleDefinition::class,
                'entity_id'
            ),

            // Parent association
            new ParentAssociationField(self::class),

            // Association without ApiAware flag
            new OneToManyAssociationField(
                'notApiAware',
                SimpleDefinition::class,
                'parent_id'
            ),

            // Association with ApiAware but only for AdminApiSource
            (new ManyToOneAssociationField(
                'adminOnly',
                'admin_id',
                SimpleDefinition::class,
                'id'
            ))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
