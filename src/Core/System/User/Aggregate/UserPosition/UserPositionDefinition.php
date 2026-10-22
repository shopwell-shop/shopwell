<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserPosition;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Position\PositionDefinition;
use Shopwell\Core\System\User\UserDefinition;

#[Package('fundamentals@framework')]
class UserPositionDefinition extends MappingEntityDefinition
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

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('position_id', 'positionId', PositionDefinition::class))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            (new ManyToOneAssociationField('position', 'position_id', PositionDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}
