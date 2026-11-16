<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Workspace;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ContentSystem\Workspace\ContentWorkspaceTest
 */
#[Package('framework')]
class ContentWorkspaceDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'content_workspace';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContentWorkspaceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContentWorkspaceCollection::class;
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    protected function defaultFields(): array
    {
        return [
            (new CreatedAtField())->addFlags(new ApiAware(AdminApiSource::class)),
            (new UpdatedAtField())->addFlags(new ApiAware(AdminApiSource::class)),
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new PrimaryKey(), new Required()),
            (new StringField('name', 'name', 255))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new JsonField('configuration', 'configuration'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new CreatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]))->addFlags(new ApiAware(AdminApiSource::class)),
            (new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
