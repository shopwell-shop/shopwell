<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * A single navigation entry as declared by an {@link AdminMenuDefinitionProviderInterface}.
 *
 * The declaration is the default of a menu item: the plugin lifecycle seeds it into the
 * database, where an administrator may customize every field afterwards. The `title` is
 * the label the administration falls back to - a snippet key for every core and plugin
 * entry, so the label stays translatable until an administrator overrides it.
 *
 * `position` orders the entry among its siblings; it is materialized into the
 * `after_menu_item_id` chain when the definition is seeded, so the database only ever
 * knows the chain and the declaration only ever knows the position.
 */
#[Package('fundamentals@framework')]
final class AdminMenuDefinitionStruct extends Struct
{
    /**
     * @param string $internalKey Module navigation id or admin route name the entry points at
     * @param string $type One of the AdminMenuItemDefinition TYPE_* constants
     * @param string $parent Internal key of the parent entry, null for top level entries
     */
    public function __construct(
        public string $internalKey,
        public string $type = AdminMenuItemDefinition::TYPE_MENU,
        public ?string $title = null,
        public ?string $icon = null,
        public ?string $color = null,
        public ?string $parent = null,
        public ?int $position = null,
        public ?string $privilege = null,
        public ?string $linkTarget = null,
    ) {
    }
}
