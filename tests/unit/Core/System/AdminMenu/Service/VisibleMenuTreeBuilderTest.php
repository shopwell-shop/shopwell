<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\AclRoleLoader;
use Shopwell\Core\Framework\Api\Acl\AclRoles;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionProviderInterface;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\MenuGroupNormalizer;
use Shopwell\Core\System\AdminMenu\Service\VisibleMenuTreeBuilder;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(VisibleMenuTreeBuilder::class)]
class VisibleMenuTreeBuilderTest extends TestCase
{
    private const USER_ID = '0f8f7a5b1c2d4e3f8a9b0c1d2e3f4a5b';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testOrdersSiblingsByTheirAfterChainNotByInsertionOrder(): void
    {
        $items = [
            $this->createItem('c2', internalKey: 'second', parentId: 'root', afterMenuItemId: 'c1'),
            $this->createItem('c1', internalKey: 'first', parentId: 'root'),
            $this->createItem('c3', internalKey: 'third', parentId: 'root', afterMenuItemId: 'c2'),
            $this->createItem('root', type: AdminMenuItemDefinition::TYPE_FOLDER, internalKey: 'sw-content'),
        ];

        $tree = $this->createBuilder($items, $this->createRegistry(...$this->definitionsFor('sw-content', 'first', 'second', 'third')))->build($this->context);

        static::assertSame(['sw-content', 'first', 'second', 'third'], $this->flatten($tree));
    }

    public function testIncludesGroupMetadataForStoredAndSynthesizedSettings(): void
    {
        $item = $this->createItem('tax', internalKey: 'sw-settings-tax');
        $item->setGroupName('localization');
        $item->setGroupTitle('Regional settings');
        $registry = $this->createRegistry(
            new AdminMenuDefinitionStruct('sw-settings-tax', groupName: 'localization'),
            new AdminMenuDefinitionStruct('sw-settings-currency', groupName: 'localization')
        );

        $tree = $this->createBuilder([$item], $registry)->build($this->context);

        static::assertSame('localization', $tree[0]['groupName']);
        static::assertSame('Regional settings', $tree[0]['groupTitle']);
        static::assertSame('group', $tree[1]['type']);
        static::assertSame('sw-system.menu.groups.localization', $tree[1]['title']);
        static::assertSame('sw-settings-currency', $tree[1]['children'][0]['internalKey']);
    }

    public function testStoredGroupsUseDeclaredLabelsAndLegacyIdentityWhenTranslationsAreMissing(): void
    {
        $key = MenuGroupNormalizer::key('sw-settings', 'customer', null);
        $settings = $this->createItem('settings', type: 'folder', internalKey: 'sw-settings');
        $group = $this->createItem('customer-group', type: 'group', internalKey: $key, parentId: 'settings');
        $group->setTitle(null);
        $page = $this->createItem('customer', internalKey: 'customer', parentId: 'customer-group');
        $registry = $this->createRegistry(
            new AdminMenuDefinitionStruct('sw-settings', type: 'folder'),
            new AdminMenuDefinitionStruct('customer', parent: 'sw-settings', groupName: 'customer')
        );
        $builder = $this->createBuilder([$settings, $group, $page], $registry);

        $tree = $builder->build($this->context);

        static::assertSame('sw-system.menu.groups.customer', $tree[0]['children'][0]['title']);
        static::assertSame('customer', $tree[0]['children'][0]['groupName']);

        $group->setTitle('Customer tools');
        $tree = $this->createBuilder([$settings, $group, $page], $registry)->build($this->context);

        static::assertSame('Customer tools', $tree[0]['children'][0]['title']);
    }

    public function testEmptyGroupsAreOmittedAndGroupChildrenRemainNested(): void
    {
        $empty = $this->createItem('empty', type: AdminMenuItemDefinition::TYPE_GROUP);
        $group = $this->createItem('group', type: AdminMenuItemDefinition::TYPE_GROUP);
        $page = $this->createItem('page', internalKey: 'product', parentId: 'group');
        $tree = $this->createBuilder([$empty, $group, $page], $this->createRegistry(new AdminMenuDefinitionStruct('product')))->build($this->context);
        static::assertCount(1, $tree);
        static::assertSame('group', $tree[0]['id']);
        static::assertSame('page', $tree[0]['children'][0]['id']);
    }

    public function testCustomTargetDoesNotChangeTheDeclarationIdentity(): void
    {
        $page = $this->createItem('page', internalKey: 'identity');
        $page->setRouteKey('target');
        $tree = $this->createBuilder([$page], $this->createRegistry(new AdminMenuDefinitionStruct('target')))->build($this->context);
        static::assertSame('identity', $tree[0]['internalKey']);
        static::assertSame('target', $tree[0]['routeKey']);
    }

    public function testHidesEntriesOfDeactivatedPlugin(): void
    {
        $plugin = $this->createPlugin('deactivated', active: false);

        $items = [$this->createItem('item', internalKey: 'sw-product', plugin: $plugin)];

        $tree = $this->createBuilder($items, $this->createRegistry(new AdminMenuDefinitionStruct('sw-product')))->build($this->context);

        static::assertSame([], $tree);
    }

    public function testHidesOrphanedEntriesWhosePluginIsGone(): void
    {
        $item = $this->createItem('item', internalKey: 'sw-product');
        $item->setPluginId('missing-plugin-id');

        $tree = $this->createBuilder([$item], $this->createRegistry(new AdminMenuDefinitionStruct('sw-product')))->build($this->context);

        static::assertSame([], $tree);
    }

    public function testShowsEntriesOfActivePlugin(): void
    {
        $plugin = $this->createPlugin('active', active: true);

        $items = [$this->createItem('item', internalKey: 'sw-product', plugin: $plugin)];

        $tree = $this->createBuilder($items, $this->createRegistry(new AdminMenuDefinitionStruct('sw-product')))->build($this->context);

        static::assertSame(['sw-product'], $this->flatten($tree));
    }

    public function testHidesMenuEntriesWhoseRouteIsNotDeclared(): void
    {
        $items = [
            $this->createItem('known', internalKey: 'sw-product'),
            $this->createItem('unknown', internalKey: 'sw.renamed.route'),
        ];

        $tree = $this->createBuilder($items, $this->createRegistry(new AdminMenuDefinitionStruct('sw-product')))->build($this->context);

        static::assertSame(['sw-product'], $this->flatten($tree));
    }

    public function testHidesEntriesBoundToRolesTheUserDoesNotHold(): void
    {
        $items = [$this->createItem('item', internalKey: 'sw-product', roles: $this->createRoles('role-b'))];

        $tree = $this->createBuilder(
            $items,
            $this->createRegistry(new AdminMenuDefinitionStruct('sw-product')),
            ['role-a']
        )->build($this->createUserContext());

        static::assertSame([], $tree);
    }

    public function testShowsEntriesBoundToARoleTheUserHolds(): void
    {
        $items = [$this->createItem('item', internalKey: 'sw-product', roles: $this->createRoles('role-a', 'role-b'))];

        $tree = $this->createBuilder(
            $items,
            $this->createRegistry(new AdminMenuDefinitionStruct('sw-product')),
            ['role-a']
        )->build($this->createUserContext());

        static::assertSame(['sw-product'], $this->flatten($tree));
    }

    public function testAdministratorsSeeEntriesBoundToAnyRole(): void
    {
        $source = new AdminApiSource(self::USER_ID);
        $source->setIsAdmin(true);

        $items = [$this->createItem('item', internalKey: 'sw-product', roles: $this->createRoles('role-b'))];

        $tree = $this->createBuilder(
            $items,
            $this->createRegistry(new AdminMenuDefinitionStruct('sw-product')),
            ['role-a']
        )->build(new Context($source));

        static::assertSame(['sw-product'], $this->flatten($tree));
    }

    public function testBuildsCoreEntriesTheDatabaseDoesNotKnowYet(): void
    {
        $registry = $this->createRegistry(
            new AdminMenuDefinitionStruct('sw-content', type: AdminMenuItemDefinition::TYPE_FOLDER, title: 'global.sw-admin-menu.navigation.mainMenuItemContent'),
            new AdminMenuDefinitionStruct('sw-blog', title: 'sw-blog.general.mainMenuItemGeneral', parent: 'sw-content')
        );

        // only an unrelated administrator-created entry is stored
        $items = [$this->createItem('custom', type: AdminMenuItemDefinition::TYPE_FOLDER, internalKey: 'my-folder')];

        $tree = $this->createBuilder($items, $registry)->build($this->context);

        static::assertSame(['my-folder', 'sw-content', 'sw-blog'], $this->flatten($tree));
        static::assertCount(1, $tree[1]['children']);
        static::assertSame('sw-blog.general.mainMenuItemGeneral', $tree[1]['children'][0]['title']);
    }

    public function testDoesNotResurrectAStoredEntryThatIsHidden(): void
    {
        $registry = $this->createRegistry(new AdminMenuDefinitionStruct('sw-product'));

        $items = [$this->createItem('item', type: AdminMenuItemDefinition::TYPE_HIDDEN, internalKey: 'sw-product')];

        $tree = $this->createBuilder($items, $registry)->build($this->context);

        static::assertSame([], $tree);
    }

    public function testDropsChildrenWhoseParentIsNotVisible(): void
    {
        $items = [
            $this->createItem('folder', type: AdminMenuItemDefinition::TYPE_HIDDEN, internalKey: 'sw-content'),
            $this->createItem('child', internalKey: 'sw-product', parentId: 'folder'),
        ];

        $tree = $this->createBuilder($items, $this->createRegistry(...$this->definitionsFor('sw-content', 'sw-product')))->build($this->context);

        // the hidden folder does not render, and it must not be re-synthesized from its declaration either
        static::assertSame([], $tree);
    }

    /**
     * @param list<AdminMenuItemEntity> $items
     * @param list<string>|null $roleIds null for an unrestricted administrator
     */
    private function createBuilder(array $items, AdminMenuRouteRegistry $registry, ?array $roleIds = null): VisibleMenuTreeBuilder
    {
        $roleLoader = static::createStub(AclRoleLoader::class);
        $roleLoader->method('getUserRoles')->willReturn([
            self::USER_ID => new AclRoles(false, $roleIds ?? []),
        ]);

        return new VisibleMenuTreeBuilder(
            StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection($items)]),
            $registry,
            $roleLoader
        );
    }

    private function createUserContext(): Context
    {
        return new Context(new AdminApiSource(self::USER_ID));
    }

    private function createRegistry(AdminMenuDefinitionStruct ...$definitions): AdminMenuRouteRegistry
    {
        $provider = new class(array_values($definitions)) implements AdminMenuDefinitionProviderInterface {
            /**
             * @param list<AdminMenuDefinitionStruct> $definitions
             */
            public function __construct(private readonly array $definitions)
            {
            }

            public function getPluginTechnicalName(): ?string
            {
                return null;
            }

            public function getDefinitions(): array
            {
                return $this->definitions;
            }
        };

        return new AdminMenuRouteRegistry(new MenuDefinitionCollector([$provider]));
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    private function definitionsFor(string ...$internalKeys): array
    {
        return array_map(
            static fn (string $internalKey): AdminMenuDefinitionStruct => new AdminMenuDefinitionStruct($internalKey),
            array_values($internalKeys)
        );
    }

    private function createRoles(string ...$roleIds): AclRoleCollection
    {
        return new AclRoleCollection(array_map(static function (string $roleId): AclRoleEntity {
            $role = new AclRoleEntity();
            $role->setId($roleId);

            return $role;
        }, $roleIds));
    }

    private function createPlugin(string $id, bool $active): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId($id);
        $plugin->assign([
            'name' => 'SwagExample',
            'active' => $active,
        ]);

        return $plugin;
    }

    private function createItem(
        string $id,
        string $type = AdminMenuItemDefinition::TYPE_MENU,
        ?string $internalKey = null,
        ?string $parentId = null,
        ?string $afterMenuItemId = null,
        ?PluginEntity $plugin = null,
        ?AclRoleCollection $roles = null,
    ): AdminMenuItemEntity {
        $item = new AdminMenuItemEntity();
        $item->setId($id);
        $item->setType($type);
        $item->setInternalKey($internalKey);
        $item->setParentId($parentId);
        $item->setAfterMenuItemId($afterMenuItemId);
        $item->setTitle($internalKey);
        $item->setActive(true);
        $item->setSysLocked(false);
        $item->setConfigOverridden(false);
        $item->setArchived(false);

        if ($plugin !== null) {
            $item->setPlugin($plugin);
            $item->setPluginId($plugin->getId());
        }

        if ($roles !== null) {
            $item->setRoles($roles);
        }

        return $item;
    }

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return list<string>
     */
    private function flatten(array $tree): array
    {
        $internalKeys = [];

        foreach ($tree as $node) {
            $internalKeys[] = (string) $node['internalKey'];
            $internalKeys = [...$internalKeys, ...$this->flatten($node['children'])];
        }

        return $internalKeys;
    }
}
