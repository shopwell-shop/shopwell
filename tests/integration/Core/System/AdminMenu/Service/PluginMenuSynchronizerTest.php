<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\AdminMenu\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginEntity;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionProviderInterface;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\CoreAdminMenuProvider;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\Service\PluginMenuSynchronizer;

/**
 * Runs the whole plugin lifecycle matrix against the database: install, activate,
 * deactivate, update, uninstall with and without keeping the user data, and the reinstall
 * after both variants. Every step asserts that no row is duplicated and that nothing an
 * administrator changed is lost.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class PluginMenuSynchronizerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const PLUGIN_NAME = 'SwagAdminMenuFixture';

    private const FOLDER_KEY = 'swag-admin-menu';

    private Context $context;

    /**
     * @var EntityRepository<AdminMenuItemCollection>
     */
    private EntityRepository $menuItemRepository;

    private PluginEntity $plugin;

    /**
     * @var list<AdminMenuDefinitionStruct>
     */
    private array $declarations = [];

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->menuItemRepository = static::getContainer()->get('admin_menu_item.repository');
        $this->plugin = $this->createPlugin();
        $this->declarations = $this->defaultDeclarations();
    }

    public function testInstallSeedsTheEntriesInvisible(): void
    {
        $this->createSynchronizer()->syncInstall($this->plugin);

        $items = $this->searchItems();

        static::assertCount(3, $items);

        foreach ($items as $item) {
            static::assertFalse($item->isActive());
            static::assertFalse($item->isArchived());
            // the seeding is not an administrator customization
            static::assertFalse($item->isConfigOverridden());
            static::assertSame($this->plugin->getId(), $item->getPluginId());
            static::assertFalse($item->isSysLocked());
            static::assertNotNull($item->getDefaultMeta());
        }
    }

    public function testInstallMaterializesThePositionIntoTheChain(): void
    {
        $this->createSynchronizer()->syncInstall($this->plugin);

        $items = $this->searchItems();
        $folder = $items[self::FOLDER_KEY];

        // the child with the declared position leads the level, the one without follows it
        $settings = $items['swag-admin-menu-settings'];
        $list = $items['swag-admin-menu-list'];

        static::assertSame($folder->getId(), $settings->getParentId());
        static::assertSame($folder->getId(), $list->getParentId());
        static::assertNull($settings->getAfterMenuItemId());
        static::assertSame($settings->getId(), $list->getAfterMenuItemId());
        static::assertNull($folder->getParentId());
    }

    public function testInstallingTwiceKeepsTheSameRows(): void
    {
        $synchronizer = $this->createSynchronizer();

        $synchronizer->syncInstall($this->plugin);
        $first = $this->searchItems();

        $synchronizer->syncInstall($this->plugin);
        $second = $this->searchItems();

        static::assertSame(array_keys($first), array_keys($second));
        static::assertSame($this->ids($first), $this->ids($second));
    }

    public function testActivationMakesTheEntriesVisibleAndDeactivationHidesThemAgain(): void
    {
        $synchronizer = $this->installAndActivate();

        foreach ($this->searchItems() as $item) {
            static::assertTrue($item->isActive());
        }

        $synchronizer->syncDeactivation($this->plugin);

        $items = $this->searchItems();

        static::assertCount(3, $items);

        foreach ($items as $item) {
            static::assertFalse($item->isActive());
            // deactivating keeps the data, so reactivating restores the same navigation
            static::assertNotNull($item->getTitle());
        }
    }

    public function testUpdateAddsNewDeclarations(): void
    {
        $synchronizer = $this->installAndActivate();

        $this->declarations[] = new AdminMenuDefinitionStruct(
            internalKey: 'swag-admin-menu-reports',
            title: 'swag-admin-menu.reports',
            parent: self::FOLDER_KEY,
            position: 20,
        );

        $synchronizer->syncUpdate($this->plugin);

        $items = $this->searchItems();

        static::assertCount(4, $items);
        static::assertArrayHasKey('swag-admin-menu-reports', $items);
        // the plugin is active, so the entry it added is visible right away
        static::assertTrue($items['swag-admin-menu-reports']->isActive());
    }

    public function testUpdateHidesAnEntryThePluginDropped(): void
    {
        $synchronizer = $this->installAndActivate();

        $this->declarations = array_values(array_filter(
            $this->declarations,
            static fn (AdminMenuDefinitionStruct $declaration): bool => $declaration->internalKey !== 'swag-admin-menu-list'
        ));

        $synchronizer->syncUpdate($this->plugin);

        $items = $this->searchItems();

        static::assertCount(3, $items);
        static::assertArrayHasKey('swag-admin-menu-list', $items);
        static::assertFalse($items['swag-admin-menu-list']->isActive());
    }

    public function testUpdateKeepsWhatAnAdministratorCustomized(): void
    {
        $synchronizer = $this->installAndActivate();

        $list = $this->searchItems()['swag-admin-menu-list'];

        $this->menuItemRepository->update([[
            'id' => $list->getId(),
            'title' => 'Eigene Liste',
            'icon' => 'regular-star',
            'configOverridden' => true,
        ]], $this->context);

        $this->declarations = array_map(
            static function (AdminMenuDefinitionStruct $declaration): AdminMenuDefinitionStruct {
                if ($declaration->internalKey !== 'swag-admin-menu-list') {
                    return $declaration;
                }

                return new AdminMenuDefinitionStruct(
                    internalKey: $declaration->internalKey,
                    title: 'swag-admin-menu.list.renamed',
                    icon: 'regular-list',
                    parent: $declaration->parent,
                    position: $declaration->position,
                );
            },
            $this->declarations
        );

        $synchronizer->syncUpdate($this->plugin);

        $updated = $this->searchItems()['swag-admin-menu-list'];

        static::assertSame('Eigene Liste', $updated->getTitle());
        static::assertSame('regular-star', $updated->getIcon());
    }

    public function testUninstallKeepingTheUserDataArchivesTheEntries(): void
    {
        $synchronizer = $this->installAndActivate();

        $synchronizer->syncUninstall($this->plugin, true);

        $items = $this->searchItems();

        static::assertCount(3, $items);

        foreach ($items as $item) {
            static::assertTrue($item->isArchived());
            static::assertFalse($item->isActive());
        }
    }

    public function testReinstallingAnArchivedPluginRevivesTheSameRows(): void
    {
        $synchronizer = $this->installAndActivate();
        $before = $this->searchItems();

        $synchronizer->syncUninstall($this->plugin, true);
        $synchronizer->syncInstall($this->plugin);
        $this->plugin = $this->pluginWithActiveState(true);
        $synchronizer->syncActivation($this->plugin);

        $after = $this->searchItems();

        static::assertSame($this->ids($before), $this->ids($after));

        foreach ($after as $item) {
            static::assertFalse($item->isArchived());
            static::assertTrue($item->isActive());
        }
    }

    public function testUninstallWithoutKeepingTheUserDataRemovesTheEntriesAndTheirBindings(): void
    {
        $synchronizer = $this->installAndActivate();

        $ids = $this->ids($this->searchItems());
        $this->bindRole(array_values($ids)[0]);

        $synchronizer->syncUninstall($this->plugin, false);

        static::assertSame([], $this->searchItems());
        static::assertSame(0, $this->countBindings($ids));
        static::assertSame(0, $this->countTranslations($ids));
    }

    public function testReinstallingAfterACleanUninstallRecreatesTheEntries(): void
    {
        $synchronizer = $this->installAndActivate();

        $synchronizer->syncUninstall($this->plugin, false);
        $synchronizer->syncInstall($this->plugin);
        $this->plugin = $this->pluginWithActiveState(true);
        $synchronizer->syncActivation($this->plugin);

        $items = $this->searchItems();

        static::assertCount(3, $items);

        foreach ($items as $item) {
            static::assertTrue($item->isActive());
            static::assertFalse($item->isArchived());
        }
    }

    public function testAnEntryBelowACoreNavigationSeedsThatCoreEntryOnce(): void
    {
        $this->declarations = [
            new AdminMenuDefinitionStruct(
                internalKey: 'swag-admin-menu-product-tab',
                title: 'swag-admin-menu.product-tab',
                parent: 'sw-catalogue',
                position: 30,
            ),
        ];

        $synchronizer = $this->installAndActivate();

        $parentId = $this->searchItems()['swag-admin-menu-product-tab']->getParentId();
        static::assertNotNull($parentId);

        $core = $this->menuItemRepository->search(new Criteria([$parentId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(AdminMenuItemEntity::class, $core);
        static::assertSame('sw-catalogue', $core->getInternalKey());
        static::assertNull($core->getPluginId());
        static::assertTrue($core->isSysLocked());

        // a second sync must not seed the core entry twice
        $synchronizer->syncUpdate($this->plugin);

        $coreItems = $this->menuItemRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('internalKey', 'sw-catalogue'))
                ->addFilter(new EqualsFilter('pluginId', null)),
            $this->context
        );

        static::assertSame(1, $coreItems->getTotal());
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    private function defaultDeclarations(): array
    {
        return [
            new AdminMenuDefinitionStruct(
                internalKey: self::FOLDER_KEY,
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'swag-admin-menu.general.mainMenuItemGeneral',
                icon: 'regular-shopping-bag',
                position: 30,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'swag-admin-menu-settings',
                title: 'swag-admin-menu.general.settings',
                parent: self::FOLDER_KEY,
                position: 10,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'swag-admin-menu-list',
                title: 'swag-admin-menu.general.list',
                parent: self::FOLDER_KEY,
            ),
        ];
    }

    /**
     * Mirrors what the lifecycle does: install the plugin, flip the plugin row to active -
     * the synchronizer reads that flag when it refreshes the items - and activate the menu.
     */
    private function installAndActivate(): PluginMenuSynchronizer
    {
        $synchronizer = $this->createSynchronizer();
        $synchronizer->syncInstall($this->plugin);

        $this->plugin = $this->pluginWithActiveState(true);
        $synchronizer->syncActivation($this->plugin);

        return $synchronizer;
    }

    private function pluginWithActiveState(bool $active): PluginEntity
    {
        static::getContainer()->get('plugin.repository')->update([[
            'id' => $this->plugin->getId(),
            'active' => $active,
        ]], $this->context);

        $plugin = static::getContainer()->get('plugin.repository')
            ->search(new Criteria([$this->plugin->getId()]), $this->context)
            ->getEntities()
            ->get($this->plugin->getId());

        static::assertInstanceOf(PluginEntity::class, $plugin);

        return $plugin;
    }

    private function createSynchronizer(): PluginMenuSynchronizer
    {
        $declarations = fn (): array => $this->declarations;

        $provider = new class($declarations, $this->plugin->getName()) implements AdminMenuDefinitionProviderInterface {
            /**
             * @param \Closure(): list<AdminMenuDefinitionStruct> $declarations
             */
            public function __construct(
                private readonly \Closure $declarations,
                private readonly string $pluginName,
            ) {
            }

            public function getPluginTechnicalName(): string
            {
                return $this->pluginName;
            }

            public function getDefinitions(): array
            {
                return ($this->declarations)();
            }
        };

        return new PluginMenuSynchronizer(
            $this->menuItemRepository,
            new AdminMenuRouteRegistry(new MenuDefinitionCollector([new CoreAdminMenuProvider(), $provider]))
        );
    }

    private function createPlugin(): PluginEntity
    {
        $pluginId = Uuid::randomHex();

        static::getContainer()->get('plugin.repository')->create([[
            'id' => $pluginId,
            'name' => self::PLUGIN_NAME,
            'baseClass' => 'SwagAdminMenuFixture\\SwagAdminMenuFixture',
            'version' => '1.0.0',
            'active' => false,
            'autoload' => [],
            'label' => 'Admin menu fixture',
        ]], $this->context);

        $plugin = static::getContainer()->get('plugin.repository')
            ->search(new Criteria([$pluginId]), $this->context)
            ->getEntities()
            ->get($pluginId);

        static::assertInstanceOf(PluginEntity::class, $plugin);

        return $plugin;
    }

    /**
     * @return array<string, AdminMenuItemEntity> indexed by internal key
     */
    private function searchItems(): array
    {
        $items = $this->menuItemRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('pluginId', $this->plugin->getId())),
            $this->context
        )->getEntities();

        $indexed = [];

        foreach ($items as $item) {
            static::assertInstanceOf(AdminMenuItemEntity::class, $item);
            $indexed[$item->getInternalKey() ?? $item->getId()] = $item;
        }

        return $indexed;
    }

    /**
     * @param array<string, AdminMenuItemEntity> $items
     *
     * @return list<string>
     */
    private function ids(array $items): array
    {
        $ids = array_map(static fn (AdminMenuItemEntity $item): string => $item->getId(), array_values($items));
        sort($ids);

        return $ids;
    }

    private function bindRole(string $itemId): void
    {
        $roleId = Uuid::randomHex();

        static::getContainer()->get('acl_role.repository')->create([[
            'id' => $roleId,
            'name' => 'Fixture',
            'privileges' => [],
        ]], $this->context);

        $this->menuItemRepository->update([[
            'id' => $itemId,
            'roles' => [['id' => $roleId]],
        ]], $this->context);
    }

    /**
     * The mapping entity has no entity class, so the bindings are counted on the table.
     *
     * @param list<string> $itemIds
     */
    private function countBindings(array $itemIds): int
    {
        if ($itemIds === []) {
            return 0;
        }

        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM admin_menu_item_role WHERE admin_menu_item_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($itemIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $itemIds
     */
    private function countTranslations(array $itemIds): int
    {
        if ($itemIds === []) {
            return 0;
        }

        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM admin_menu_item_translation WHERE admin_menu_item_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($itemIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
