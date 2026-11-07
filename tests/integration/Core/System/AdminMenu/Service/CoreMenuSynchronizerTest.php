<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\AdminMenu\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
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
use Shopwell\Core\System\AdminMenu\Service\CoreMenuRowsBuilder;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;

/**
 * The installation migration seeds the rows, so this service is what an upgrade, a restored
 * backup or a cleaned up table needs. These cases run it against the database - the first
 * run, the second run, a release that relabels an entry, a row that went missing and an
 * entry an administrator arranged differently.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class CoreMenuSynchronizerTest extends TestCase
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

    public function testSeedsEveryCoreDeclaration(): void
    {
        $written = $this->createSynchronizer()->synchronize();

        $items = $this->coreItems();
        $declarations = $this->coreDefinitions();

        static::assertSame(\count($declarations), $written);
        static::assertCount(\count($declarations), $items);

        foreach ($declarations as $declaration) {
            $item = $items[$declaration->internalKey];

            static::assertTrue($item->isActive());
            static::assertFalse($item->isArchived());
            static::assertFalse($item->isConfigOverridden());
            // a core entry belongs to the product: customizable, but not deletable
            static::assertTrue($item->isSysLocked());
            static::assertNull($item->getPluginId());
            // the label is not part of the row: the declaration carries a snippet key, and the
            // navigation falls back to it until a person writes a label of their own
            static::assertNull($item->getTitle());
            static::assertSame($declaration->groupName, $item->getGroupName());
            static::assertNotNull($item->getDefaultMeta());
        }
    }

    public function testMaterializesTheDeclaredHierarchyAndOrder(): void
    {
        $this->createSynchronizer()->synchronize();

        $items = $this->coreItems();

        static::assertNull($items['sw-dashboard']->getParentId());
        static::assertNull($items['sw-dashboard']->getAfterMenuItemId());

        // the second top level entry follows the first one instead of a missing anchor
        static::assertSame($items['sw-dashboard']->getId(), $items['sw-catalogue']->getAfterMenuItemId());

        static::assertSame($items['sw-catalogue']->getId(), $items['sw-product']->getParentId());
        static::assertNull($items['sw-product']->getAfterMenuItemId());
        // position 20 follows position 10, position 25 follows position 20
        static::assertSame($items['sw-product']->getId(), $items['sw-review']->getAfterMenuItemId());
        static::assertSame($items['sw-review']->getId(), $items['sw-category']->getAfterMenuItemId());
    }

    public function testTheSecondRunWritesNothing(): void
    {
        static::assertGreaterThan(0, $this->createSynchronizer()->synchronize());

        $before = $this->ids($this->coreItems());

        static::assertSame(0, $this->createSynchronizer()->synchronize());

        static::assertSame($before, $this->ids($this->coreItems()));
    }

    /**
     * Two requests can reach an empty table at the same time; both compute the same id, so
     * the second write updates the row the first one created instead of duplicating it.
     */
    public function testSeedingTwiceFromTwoInstancesKeepsOneRowPerEntry(): void
    {
        $first = $this->createSynchronizer();
        $second = $this->createSynchronizer();

        $first->synchronize();
        $second->synchronize();

        $items = $this->coreItems();

        static::assertCount(\count($this->coreDefinitions()), $items);

        foreach ($items as $internalKey => $item) {
            static::assertSame(1, $this->countByInternalKey($internalKey), $internalKey);
        }
    }

    public function testDoesNotTouchAnItemAnAdministratorCustomized(): void
    {
        $this->createSynchronizer()->synchronize();

        $review = $this->coreItems()['sw-review'];

        $this->menuItemRepository->update([[
            'id' => $review->getId(),
            'title' => 'Bewertungen',
            'icon' => 'regular-star',
            'configOverridden' => true,
        ]], $this->context);

        $this->createSynchronizer($this->renamedProvider('sw-review', 'sw-review.general.mainMenuItemList'))->synchronize();

        $customized = $this->coreItems()['sw-review'];

        static::assertSame('Bewertungen', $customized->getTitle());
        static::assertSame('regular-star', $customized->getIcon());
    }

    public function testRefreshesAnEntryACoreUpgradeChanged(): void
    {
        $this->createSynchronizer()->synchronize();

        // the release changes a field the row owns, so the row follows the declaration
        $this->createSynchronizer($this->providerWith('sw-media', ['icon' => 'regular-image-plus']))->synchronize();

        static::assertSame('regular-image-plus', $this->coreItems()['sw-media']->getIcon());
    }

    public function testDoesNotWriteARenamedLabelIntoTheTable(): void
    {
        $this->createSynchronizer()->synchronize();

        // a renamed declaration is a label change in code - writing it into the table would put
        // a snippet key there and show it to the next administrator who edits the entry
        $this->createSynchronizer($this->renamedProvider('sw-media', 'sw-media.general.mainMenuItemRenamed'))->synchronize();

        static::assertNull($this->coreItems()['sw-media']->getTitle());
    }

    public function testRecreatesARowThatWentMissing(): void
    {
        $this->createSynchronizer()->synchronize();

        $media = $this->coreItems()['sw-media'];

        // simulates a table someone cleaned up: the row is gone, the declaration is not
        $this->connection()->executeStatement(
            'DELETE FROM admin_menu_item WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($media->getId())]
        );

        static::assertGreaterThan(0, $this->createSynchronizer()->synchronize());

        $restored = $this->coreItems()['sw-media'];

        static::assertSame($this->coreItems()['sw-content']->getId(), $restored->getParentId());
        static::assertSame($media->getId(), $restored->getId());
    }

    public function testKeepsThePlaceAnAdministratorGaveAnEntry(): void
    {
        $this->createSynchronizer()->synchronize();

        $manufacturer = $this->coreItems()['sw-manufacturer'];

        // the administrator drags the last entry of the level to the front
        $this->menuItemRepository->update([[
            'id' => $manufacturer->getId(),
            'afterMenuItemId' => null,
            'configOverridden' => true,
        ]], $this->context);

        // a write is needed for the chain to be rewritten at all
        $this->createSynchronizer($this->renamedProvider('sw-media', 'sw-media.general.mainMenuItemRenamed'))->synchronize();

        static::assertNull($this->coreItems()['sw-manufacturer']->getAfterMenuItemId());
    }

    public function testDeletesARowTheCoreNoLongerDeclares(): void
    {
        $this->createSynchronizer()->synchronize();

        // a release drops an entry entirely: the row must not survive as a ghost
        $dropped = new class implements AdminMenuDefinitionProviderInterface {
            public function getPluginTechnicalName(): ?string
            {
                return null;
            }

            public function getDefinitions(): array
            {
                return array_values(array_filter(
                    (new CoreAdminMenuProvider())->getDefinitions(),
                    static fn (AdminMenuDefinitionStruct $declaration): bool => $declaration->internalKey !== 'sw-media',
                ));
            }
        };

        $written = $this->createSynchronizer($dropped)->synchronize();

        static::assertGreaterThan(0, $written);
        static::assertSame(0, $this->countByInternalKey('sw-media'));

        // a later release brings the entry back: the sync recreates it under its id
        $this->createSynchronizer()->synchronize();

        static::assertSame(1, $this->countByInternalKey('sw-media'));
        static::assertSame(
            $this->coreItems()['sw-content']->getId(),
            $this->coreItems()['sw-media']->getParentId()
        );
    }

    public function testDeletesACustomizedRowTheCoreNoLongerDeclares(): void
    {
        $this->createSynchronizer()->synchronize();

        $review = $this->coreItems()['sw-review'];

        $this->menuItemRepository->update([[
            'id' => $review->getId(),
            'title' => 'Bewertungen',
            'configOverridden' => true,
        ]], $this->context);

        // the declaration decides whether a core row exists at all - a customization does
        // not keep a dropped entry alive
        $dropped = new class implements AdminMenuDefinitionProviderInterface {
            public function getPluginTechnicalName(): ?string
            {
                return null;
            }

            public function getDefinitions(): array
            {
                return array_values(array_filter(
                    (new CoreAdminMenuProvider())->getDefinitions(),
                    static fn (AdminMenuDefinitionStruct $declaration): bool => $declaration->internalKey !== 'sw-review',
                ));
            }
        };

        $this->createSynchronizer($dropped)->synchronize();

        static::assertSame(0, $this->countByInternalKey('sw-review'));
    }

    public function testReanchorsTheChildrenOfADeletedRowInsteadOfCascadingThemAway(): void
    {
        $this->createSynchronizer()->synchronize();

        // an administrator-created entry under the catalogue: the catalogue is dropped in
        // the next release, the custom entry must survive the ghost cleanup
        $customChildId = Uuid::randomHex();
        $catalogue = $this->coreItems()['sw-catalogue'];

        $this->menuItemRepository->upsert([[
            'id' => $customChildId,
            'title' => 'My custom entry',
            'type' => AdminMenuItemDefinition::TYPE_MENU,
            'parent' => ['id' => $catalogue->getId()],
            'active' => true,
        ]], $this->context);

        $dropped = new class implements AdminMenuDefinitionProviderInterface {
            public function getPluginTechnicalName(): ?string
            {
                return null;
            }

            public function getDefinitions(): array
            {
                return array_values(array_filter(
                    (new CoreAdminMenuProvider())->getDefinitions(),
                    static fn (AdminMenuDefinitionStruct $declaration): bool => $declaration->internalKey !== 'sw-catalogue',
                ));
            }
        };

        $this->createSynchronizer($dropped)->synchronize();

        static::assertSame(0, $this->countByInternalKey('sw-catalogue'));

        $custom = $this->menuItemRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('id', $customChildId)),
            $this->context
        )->getEntities()->first();

        static::assertNotNull($custom);
        // the parent is gone: the entry floats to the top level instead of dying with it
        static::assertNull($custom->getParentId());
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    private function coreDefinitions(): array
    {
        return (new CoreAdminMenuProvider())->getDefinitions();
    }

    /**
     * @param AdminMenuDefinitionProviderInterface|null $provider replaces the core provider,
     *                                                            so a case can declare the whole navigation with one entry changed instead of
     *                                                            adding a second declaration of the same entry
     */
    private function createSynchronizer(?AdminMenuDefinitionProviderInterface $provider = null): CoreMenuSynchronizer
    {
        return new CoreMenuSynchronizer(
            $this->menuItemRepository,
            new AdminMenuRouteRegistry(new MenuDefinitionCollector([$provider ?? new CoreAdminMenuProvider()])),
            new CoreMenuRowsBuilder()
        );
    }

    /**
     * Declares the given entry under a different label while keeping everything else, the
     * way a release does when it renames a menu item.
     */
    private function renamedProvider(string $internalKey, string $title): AdminMenuDefinitionProviderInterface
    {
        return $this->providerWith($internalKey, ['title' => $title]);
    }

    /**
     * Declares the given entry with the given fields replaced, the way a release changes one
     * entry of the navigation.
     *
     * @param array<string, mixed> $overrides
     */
    private function providerWith(string $internalKey, array $overrides): AdminMenuDefinitionProviderInterface
    {
        return new class($internalKey, $overrides) implements AdminMenuDefinitionProviderInterface {
            /**
             * @param array<string, mixed> $overrides
             */
            public function __construct(
                private readonly string $internalKey,
                private readonly array $overrides,
            ) {
            }

            public function getPluginTechnicalName(): ?string
            {
                return null;
            }

            public function getDefinitions(): array
            {
                $definitions = [];

                foreach ((new CoreAdminMenuProvider())->getDefinitions() as $declaration) {
                    if ($declaration->internalKey !== $this->internalKey) {
                        $definitions[] = $declaration;

                        continue;
                    }

                    $definitions[] = new AdminMenuDefinitionStruct(
                        internalKey: $declaration->internalKey,
                        type: $this->overrides['type'] ?? $declaration->type,
                        title: $this->overrides['title'] ?? $declaration->title,
                        icon: $this->overrides['icon'] ?? $declaration->icon,
                        color: $declaration->color,
                        parent: $declaration->parent,
                        position: $declaration->position,
                        privilege: $declaration->privilege,
                        linkTarget: $declaration->linkTarget,
                        groupName: $declaration->groupName,
                    );
                }

                return $definitions;
            }
        };
    }

    /**
     * @return array<string, AdminMenuItemEntity> indexed by internal key
     */
    private function coreItems(): array
    {
        $items = $this->menuItemRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('pluginId', null)),
            $this->context
        )->getEntities();

        $indexed = [];

        foreach ($items as $item) {
            static::assertInstanceOf(AdminMenuItemEntity::class, $item);
            static::assertNotNull($item->getInternalKey());
            $indexed[$item->getInternalKey()] = $item;
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

    private function countByInternalKey(string $internalKey): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM admin_menu_item WHERE internal_key = :internalKey AND plugin_id IS NULL',
            ['internalKey' => $internalKey]
        );
    }

    /**
     * Bypasses the DAL on purpose: the rows are locked against deletion, and the cleanup
     * has to be able to remove them anyway.
     */
    private function removeCoreItems(): void
    {
        $this->connection()->executeStatement('UPDATE admin_menu_item SET parent_id = NULL, after_menu_item_id = NULL WHERE plugin_id IS NULL');
        $this->connection()->executeStatement('DELETE FROM admin_menu_item WHERE plugin_id IS NULL');
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
