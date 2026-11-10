<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\Service\PluginMenuSynchronizer;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(PluginMenuSynchronizer::class)]
class PluginMenuSynchronizerTest extends TestCase
{
    public function testEmptyDeclarationsAndRowsDoNotWrite(): void
    {
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [
            static function (Criteria $criteria, Context $context): AdminMenuItemCollection {
                static::assertTrue($context->hasExtension(PluginMenuSynchronizer::CONTEXT_EXTENSION));
                static::assertSame(['pluginId'], $criteria->getFilters()[0]->getFields());

                return new AdminMenuItemCollection();
            },
        ]);
        $this->synchronizer($repository)->syncActivation($this->plugin());

        static::assertSame([], $repository->upserts);
        static::assertSame([], $repository->updates);
        static::assertSame([], $repository->deletes);
    }

    public function testInstallSeedsInvisibleRowsAndOrdersSiblingsByPosition(): void
    {
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection()]);
        $this->synchronizer(
            $repository,
            new AdminMenuDefinitionStruct('second', position: 2),
            new AdminMenuDefinitionStruct('first', position: 1)
        )->syncInstall($this->plugin());

        $items = array_column($repository->upserts[0], null, 'internalKey');
        static::assertFalse($items['first']['active']);
        static::assertFalse($items['first']['archived']);
        static::assertSame($this->plugin()->getId(), $items['first']['pluginId']);
        static::assertSame([
            ['id' => $items['first']['id'], 'parentId' => null, 'afterMenuItemId' => null],
            ['id' => $items['second']['id'], 'parentId' => null, 'afterMenuItemId' => $items['first']['id']],
        ], $repository->updates[0]);
    }

    public function testActivationRestoresAnArchivedCustomizedRowWithoutReplacingItsFieldsOrPlace(): void
    {
        $item = $this->item('merchant-item', 'customized');
        $item->setConfigOverridden(true);
        $item->setArchived(true);
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$item])]);
        $definition = new AdminMenuDefinitionStruct('customized', title: 'new.default.title', icon: 'new-icon', position: 1);
        $this->synchronizer($repository, $definition, new AdminMenuDefinitionStruct('next', position: 2))
            ->syncActivation($this->plugin());

        static::assertSame([
            'id' => 'merchant-item',
            'pluginId' => $this->plugin()->getId(),
            'internalKey' => 'customized',
            'active' => true,
            'archived' => false,
            'defaultMeta' => $definition->jsonSerialize(),
        ], $repository->upserts[0][0]);
        static::assertCount(1, $repository->updates[0]);
        static::assertSame('merchant-item', $repository->updates[0][0]['afterMenuItemId']);
    }

    public function testDroppedDeclarationsAreHiddenInsteadOfDeleted(): void
    {
        $item = $this->item('removed-id', 'removed');
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$item])]);
        $this->synchronizer($repository)->syncUpdate($this->plugin());

        static::assertSame([[['id' => 'removed-id', 'active' => false]]], $repository->updates);
        static::assertSame([], $repository->deletes);
    }

    public function testDeactivationPreservesRowsAndUninstallHonorsKeepUserData(): void
    {
        $items = new AdminMenuItemCollection([$this->item('retained-id', 'retained')]);
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [$items]);
        $synchronizer = $this->synchronizer($repository);
        $synchronizer->syncDeactivation($this->plugin());
        static::assertSame([[['id' => 'retained-id', 'active' => false]]], $repository->updates);
        static::assertSame([], $repository->deletes);

        $archived = StaticEntityRepository::of(AdminMenuItemCollection::class, [$items]);
        $this->synchronizer($archived)->syncUninstall($this->plugin(), true);
        static::assertSame([[['id' => 'retained-id', 'archived' => true, 'active' => false]]], $archived->updates);
        static::assertSame([], $archived->deletes);

        $purged = StaticEntityRepository::of(AdminMenuItemCollection::class, [$items]);
        $this->synchronizer($purged)->syncUninstall($this->plugin(), false);
        static::assertSame([[['id' => 'retained-id']]], $purged->deletes);
    }

    public function testMigratedCustomGroupSurvivesUpdatesAndFollowsPluginLifecycle(): void
    {
        $group = $this->item('group-id', 'admin-menu-group:custom');
        $group->setType('group');
        $group->setConfigOverridden(true);
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$group])]);
        $this->synchronizer($repository)->syncUpdate($this->plugin());
        static::assertSame([], $repository->updates);

        $group->setActive(false);
        $group->setArchived(true);
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$group])]);
        $this->synchronizer($repository)->syncActivation($this->plugin());
        static::assertSame([[['id' => 'group-id', 'active' => true, 'archived' => false]]], $repository->updates);

        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$group])]);
        $this->synchronizer($repository)->syncDeactivation($this->plugin());
        static::assertSame([[['id' => 'group-id', 'active' => false]]], $repository->updates);
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$group])]);
        $this->synchronizer($repository)->syncUninstall($this->plugin(), true);
        static::assertSame([[['id' => 'group-id', 'archived' => true, 'active' => false]]], $repository->updates);
    }

    /**
     * @param StaticEntityRepository<AdminMenuItemCollection> $repository
     */
    private function synchronizer(StaticEntityRepository $repository, AdminMenuDefinitionStruct ...$definitions): PluginMenuSynchronizer
    {
        $registry = static::createStub(AdminMenuRouteRegistry::class);
        $registry->method('getPluginDefinitions')->willReturn($definitions);

        return new PluginMenuSynchronizer($repository, $registry);
    }

    private function plugin(): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId('1234567890abcdef1234567890abcdef');
        $plugin->setName('ExamplePlugin');
        $plugin->setActive(true);

        return $plugin;
    }

    private function item(string $id, string $key): AdminMenuItemEntity
    {
        $item = new AdminMenuItemEntity();
        $item->assign(['id' => $id, 'internalKey' => $key, 'active' => true, 'configOverridden' => false, 'type' => 'menu', 'archived' => false]);

        return $item;
    }
}
