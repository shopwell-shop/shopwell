<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserOrganizationUnit;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;
use Shopwell\Core\System\User\UserDefinition;

/**
 * Maps users to organization units. Exactly one mapping per user
 * may carry is_primary = true (primary unit, comparable to DingTalk /
 * WeCom primary department semantics). Enforced at application level.
 */
#[Package('fundamentals@framework')]
class UserOrganizationUnitDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'user_organization_unit';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('organization_unit_id', 'organizationUnitId', OrganizationUnitDefinition::class))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new BoolField('is_primary', 'isPrimary'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            (new ManyToOneAssociationField('organizationUnit', 'organization_unit_id', OrganizationUnitDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}
