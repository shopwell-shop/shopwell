<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemRole;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class AdminMenuItemRoleDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'admin_menu_item_role';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('admin_menu_item_id', 'adminMenuItemId', AdminMenuItemDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('acl_role_id', 'aclRoleId', AclRoleDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            // a mapping definition has no default fields, so the timestamps have to be declared here
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('adminMenuItem', 'admin_menu_item_id', AdminMenuItemDefinition::class, 'id', false),
            new ManyToOneAssociationField('role', 'acl_role_id', AclRoleDefinition::class, 'id', false),
        ]);
    }
}
