<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\AdminMenu\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\AclRoleLoader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\CoreAdminMenuProvider;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuRowsBuilder;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Shopwell\Core\System\AdminMenu\Service\VisibleMenuTreeBuilder;

/**
 * The navigation the administration actually receives. Two sources feed it: the stored
 * rows, and - for every core declaration without a row - the declaration itself, so a
 * fresh installation that has not been seeded yet still renders a navigation instead of
 * an empty sidebar.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class VisibleMenuTreeBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<AdminMenuItemCollection>
     */
    private EntityRepository $menuItemRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->menuItemRepository = static::getContainer()->get('admin_menu_item.repository');
        $this->removeCoreItems();
    }

    protected function tearDown(): void
    {
        $this->removeCoreItems();
    }

    /**
     * The case an installation is in before anything seeded the table: no rows at all, and
     * the builder still has to render every core entry.
     */
    public function testRendersTheCoreDeclarationsWithoutAStoredRow(): void
    {
        $tree = $this->builder()->build($this->context);

        $flattened = $this->flatten($tree);

        static::assertCount(\count((new MenuDefinitionCollector([new CoreAdminMenuProvider()]))->collectCore()), $flattened);
        static::assertArrayHasKey('sw-system', $flattened);
        static::assertArrayHasKey('sw-permissions-menu', $flattened);
    }

    public function testRendersTheSeededNavigationInTheDeclaredOrder(): void
    {
        $this->createSynchronizer()->synchronize();

        $tree = $this->builder()->build($this->context);
        $flattened = $this->flatten($tree);

        static::assertCount(\count((new MenuDefinitionCollector([new CoreAdminMenuProvider()]))->collectCore()), $flattened);

        // the first level follows the declared positions
        static::assertSame('sw-dashboard', $tree[0]['internalKey']);
        static::assertSame('sw-catalogue', $tree[1]['internalKey']);

        // and the system menu carries the three sub menus of the redesign
        $system = $flattened['sw-system'];
        static::assertSame(
            ['sw-user', 'sw-permissions-role', 'sw-permissions-menu'],
            array_map(static fn (array $node): ?string => $node['internalKey'], $system['children'])
        );
    }

    public function testAHiddenGroupTakesItsChildrenWithIt(): void
    {
        $this->createSynchronizer()->synchronize();

        $system = $this->findNode($this->builder()->build($this->context), 'sw-system');
        static::assertNotNull($system);

        $this->menuItemRepository->update([[
            'id' => $system['id'],
            'active' => false,
        ]], $this->context);

        $flattened = $this->flatten($this->builder()->build($this->context));

        // a hidden parent and its children would be unreachable, so neither is rendered
        static::assertArrayNotHasKey('sw-system', $flattened);
        static::assertArrayNotHasKey('sw-permissions-menu', $flattened);
    }

    private function createSynchronizer(): CoreMenuSynchronizer
    {
        return new CoreMenuSynchronizer($this->menuItemRepository, $this->registry(), new CoreMenuRowsBuilder());
    }

    private function builder(): VisibleMenuTreeBuilder
    {
        return new VisibleMenuTreeBuilder(
            $this->menuItemRepository,
            $this->registry(),
            static::getContainer()->get(AclRoleLoader::class),
            static::getContainer()->get('acl_role.repository')
        );
    }

    private function registry(): AdminMenuRouteRegistry
    {
        return new AdminMenuRouteRegistry(new MenuDefinitionCollector([new CoreAdminMenuProvider()]));
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return array<string, array<string, mixed>>
     */
    private function flatten(array $nodes): array
    {
        $flattened = [];

        foreach ($nodes as $node) {
            $flattened[(string) $node['internalKey']] = $node;
            $flattened += $this->flatten($node['children'] ?? []);
        }

        return $flattened;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return array<string, mixed>|null
     */
    private function findNode(array $nodes, string $internalKey): ?array
    {
        return $this->flatten($nodes)[$internalKey] ?? null;
    }

    private function removeCoreItems(): void
    {
        // bypasses the DAL on purpose: the rows are locked against deletion
        $this->connection()->executeStatement('DELETE FROM admin_menu_item WHERE plugin_id IS NULL');
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
