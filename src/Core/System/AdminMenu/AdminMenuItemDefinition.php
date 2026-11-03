<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginDefinition;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemRole\AdminMenuItemRoleDefinition;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation\AdminMenuItemTranslationDefinition;

/**
 * Holds the administration navigation as data, so the menu tree, labels, icons,
 * ordering and role visibility can be managed in the Administration instead of
 * being hard-wired to the registered modules.
 *
 * The database only manages the navigation layer: every `menu` item has to point
 * to a route registered in code, which is validated against the route whitelist.
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class AdminMenuItemDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'admin_menu_item';

    final public const TYPE_FOLDER = 'folder';
    final public const TYPE_MENU = 'menu';
    final public const TYPE_LINK = 'link';
    final public const TYPE_HIDDEN = 'hidden';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AdminMenuItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return AdminMenuItemEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    public function getDefaults(): array
    {
        return [
            'active' => true,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the admin menu item.'),
            (new ParentFkField(self::class))->setDescription('Unique identity of the parent menu item. Top level items have no parent.'),
            (new FkField('after_menu_item_id', 'afterMenuItemId', self::class))->setDescription('Unique identity of the sibling this item is positioned after within the same parent.'),
            (new StringField('internal_key', 'internalKey'))->setDescription('Module navigation id or admin route name this item points to. Validated against the route whitelist for `menu` type items.'),
            (new StringField('group_name', 'groupName'))->setDescription('Group the item is listed under (`general`, `customer`, …). Entries without a group are listed on their own.'),
            new TranslatedField('title'),
            // the group *key* above is untranslated, the label a language shows for it is not
            new TranslatedField('groupTitle'),
            (new StringField('icon', 'icon'))->setDescription('Meteor icon name shown next to the label.'),
            (new StringField('color', 'color'))->setDescription('Module theme color of the item.'),
            (new StringField('type', 'type'))->addFlags(new Required())->setDescription('Item type: `folder` groups children, `menu` links to an admin route, `link` opens an external URL, `hidden` is kept but not rendered.'),
            (new StringField('link_target', 'linkTarget'))->setDescription('External URL for `link` type items.'),
            (new StringField('privilege', 'privilege'))->setDescription('Admin ACL privilege required to see and open this item, in addition to the role bindings.'),
            (new BoolField('active', 'active'))->setDescription('When `false`, the item is not rendered in the administration navigation.'),
            (new BoolField('sys_locked', 'sysLocked'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Set for core built-in items, which cannot be deleted but can be customized.'),
            // Core built-in items are the ones with a null pluginId, so the owning plugin is the single source of truth.
            (new FkField('plugin_id', 'pluginId', PluginDefinition::class))->setDescription('Unique identity of the plugin that registered this item. Core items have no owning plugin.'),
            (new JsonField('default_meta', 'defaultMeta'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Snapshot of the declared default metadata, used to detect changes on plugin updates and to fall back for customized items.'),
            (new BoolField('config_overridden', 'configOverridden'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Set when an administrator customized this item; plugin updates preserve customized items.'),
            (new BoolField('archived', 'archived'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Set when the owning plugin was uninstalled with user data kept; archived items stay hidden until the plugin is reinstalled.'),
            new CreatedAtField(),
            new UpdatedAtField(),

            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, 'id'),
            new ManyToManyAssociationField('roles', AclRoleDefinition::class, AdminMenuItemRoleDefinition::class, 'admin_menu_item_id', 'acl_role_id'),
            new TranslationsAssociationField(AdminMenuItemTranslationDefinition::class, 'admin_menu_item_id'),
        ]);
    }
}
