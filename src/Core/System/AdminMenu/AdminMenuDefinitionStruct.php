<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * A single navigation entry as declared by an {@link AdminMenuDefinitionProviderInterface}.
 *
 * The declaration is the default of a menu item: the plugin lifecycle seeds it into the
 * database, where an administrator may customize every field afterwards. Titles are literal
 * text. Locale-keyed translations and their custom fields are written through DAL into
 * admin_menu_item_translation; menu labels do not depend on Administration snippets.
 *
 * `position` orders the entry among its siblings; it is materialized into the
 * `after_menu_item_id` chain when the definition is seeded, so the database only ever
 * knows the chain and the declaration only ever knows the position.
 */
#[Package('fundamentals@framework')]
final class AdminMenuDefinitionStruct extends Struct
{
    /**
     * @param array<string, array{title: string, customFields?: array<string, mixed>}> $translations
     * @param string $internalKey Module navigation id or admin route name the entry points at
     * @param string $type One of the AdminMenuItemDefinition TYPE_* constants
     * @param string $parent Internal key of the parent entry, null for top level entries
     * @param string|null $groupName Group the entry is listed under (`general`, `customer`,
     *                               …); a group is optional and groups of the same name are listed together
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
        public ?string $groupName = null,
        public array $translations = [],
    ) {
    }
}
