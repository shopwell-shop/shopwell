<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification;

use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppContentSystemBindingSpecificationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_content_system_binding_specification';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppContentSystemBindingSpecificationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppContentSystemBindingSpecificationCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new JsonField('schema', 'schema'))->addFlags(new Required()),
            (new StringField('hash', 'hash', 64))->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
