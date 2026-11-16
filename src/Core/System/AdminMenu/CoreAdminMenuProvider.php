<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;

/**
 * Core menu defaults, compiled from XML by AdminMenuCompilerPass.
 *
 * @internal
 *
 * @phpstan-import-type MenuDefinition from AdminMenuXmlReader
 */
#[Package('fundamentals@framework')]
class CoreAdminMenuProvider extends XmlAdminMenuProvider
{
    public const MENU_FILE = __DIR__ . '/Resources/config/admin-menu.xml';

    /**
     * @param list<MenuDefinition> $definitions
     */
    public function __construct(array $definitions)
    {
        parent::__construct($definitions);
    }
}
