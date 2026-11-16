<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserOrganizationUnit;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;
use Shopwell\Core\System\User\UserDefinition;

/**
 * Department membership owns the primary affiliation, leadership and direct reporting line.
 * A supervisor references another membership so multi-department reporting remains explicit.
 * The pair of user and department is unique; normal users require exactly one primary membership.
 */
#[Package('fundamentals@framework')]
class UserOrganizationUnitDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'user_organization_unit';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return UserOrganizationUnitCollection::class;
    }

    public function getEntityClass(): string
    {
        return UserOrganizationUnitEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('organization_unit_id', 'organizationUnitId', OrganizationUnitDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new BoolField('is_primary', 'isPrimary'))->addFlags(new ApiAware()),
            (new BoolField('is_leader', 'isLeader'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new IntField('leader_position', 'leaderPosition'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new FkField('supervisor_membership_id', 'supervisorMembershipId', self::class))->addFlags(new ApiAware(AdminApiSource::class)),
            (new ManyToOneAssociationField('supervisorMembership', 'supervisor_membership_id', self::class))->addFlags(new ApiAware(AdminApiSource::class), new SetNullOnDelete()),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            (new ManyToOneAssociationField('organizationUnit', 'organization_unit_id', OrganizationUnitDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}
