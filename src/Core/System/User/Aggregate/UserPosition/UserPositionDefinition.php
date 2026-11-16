<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserPosition;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;
use Shopwell\Core\System\Position\PositionDefinition;
use Shopwell\Core\System\User\UserDefinition;

#[Package('fundamentals@framework')]
class UserPositionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'user_position';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return UserPositionCollection::class;
    }

    public function getEntityClass(): string
    {
        return UserPositionEntity::class;
    }

    public function since(): ?string
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
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('position_id', 'positionId', PositionDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('organization_unit_id', 'organizationUnitId', OrganizationUnitDefinition::class))->addFlags(new ApiAware(AdminApiSource::class)),
            (new BoolField('is_primary', 'isPrimary'))->addFlags(new ApiAware(AdminApiSource::class)),

            (new ManyToOneAssociationField('organizationUnit', 'organization_unit_id', OrganizationUnitDefinition::class))->addFlags(new ApiAware(AdminApiSource::class)),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            (new ManyToOneAssociationField('position', 'position_id', PositionDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}
