<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuRowsBuilder;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Shopwell\Core\System\AdminMenu\Service\PluginMenuSynchronizer;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CoreMenuSynchronizer::class)]
class CoreMenuSynchronizerTest extends TestCase
{
    public function testAnEmptyRegistryDoesNotReadOrWriteTheRepository(): void
    {
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class);

        static::assertSame(0, $this->synchronizer($repository)->synchronize());
        static::assertSame([], $repository->searchedCriteria);
        static::assertSame([], $repository->upserts);
        static::assertSame([], $repository->updates);
        static::assertSame([], $repository->deletes);
    }

    public function testSeedsLockedRowsWithSystemContextAndSynchronizesOnlyOnce(): void
    {
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [
            static function (Criteria $criteria, Context $context): AdminMenuItemCollection {
                static::assertTrue($context->hasExtension(PluginMenuSynchronizer::CONTEXT_EXTENSION));
                static::assertSame(['pluginId'], $criteria->getFilters()[0]->getFields());

                return new AdminMenuItemCollection();
            },
        ]);
        $synchronizer = $this->synchronizer($repository, new AdminMenuDefinitionStruct('sw-customer', icon: 'regular-user'));

        static::assertSame(1, $synchronizer->synchronize());
        static::assertSame(0, $synchronizer->synchronize());
        static::assertCount(1, $repository->searchedCriteria);
        static::assertCount(1, $repository->upserts);
        $payload = $repository->upserts[0][0];
        static::assertSame((new CoreMenuRowsBuilder())->idFor('sw-customer'), $payload['id']);
        static::assertSame('regular-user', $payload['icon']);
        static::assertTrue($payload['sysLocked']);
        static::assertTrue($payload['active']);
        static::assertFalse($payload['archived']);
        static::assertFalse($payload['configOverridden']);
    }

    public function testRenamedCoreEntriesKeepTheirStoredIdentityAndFollowingSibling(): void
    {
        $item = $this->item('sw-user');
        $item->setId('original-menu-id');
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$item])]);
        $this->synchronizer(
            $repository,
            new AdminMenuDefinitionStruct('sw-user', icon: 'new-icon', position: 1),
            new AdminMenuDefinitionStruct('sw-next', position: 2)
        )->synchronize();
        static::assertSame('original-menu-id', $repository->upserts[0][0]['id']);
        static::assertSame('original-menu-id', $repository->upserts[0][1]['afterMenuItemId']);
    }

    public function testMatchingRowsDoNotWriteAndTranslatedTitlesAreNotReset(): void
    {
        $item = $this->item('sw-customer');
        $item->setTitle('Customers chosen by the merchant');
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$item])]);

        static::assertSame(0, $this->synchronizer($repository, new AdminMenuDefinitionStruct('sw-customer', title: 'snippet.customer'))->synchronize());
        static::assertSame([], $repository->upserts);
        static::assertSame([], $repository->updates);
        static::assertSame([], $repository->deletes);
    }

    public function testCustomizedRowsStayUntouchedAndAnchorTheirFollowingSibling(): void
    {
        $customized = $this->item('sw-customer');
        $customized->setConfigOverridden(true);
        $customized->setIcon('merchant-icon');
        $customized->setId('merchant-chosen-id');
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$customized])]);

        static::assertSame(1, $this->synchronizer(
            $repository,
            new AdminMenuDefinitionStruct('sw-customer', icon: 'new-core-icon', position: 1),
            new AdminMenuDefinitionStruct('sw-order', position: 2)
        )->synchronize());

        static::assertCount(1, $repository->upserts[0]);
        static::assertSame('sw-order', $repository->upserts[0][0]['internalKey']);
        static::assertSame('merchant-chosen-id', $repository->upserts[0][0]['afterMenuItemId']);
        static::assertSame([], $repository->updates);
    }

    public function testMovesUndeclaredChildrenBeforeDeletingTheirObsoleteParent(): void
    {
        $ghost = $this->item('removed-route');
        $child = $this->item('custom-child');
        $child->setInternalKey(null);
        $child->setParentId($ghost->getId());
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [
            new AdminMenuItemCollection([$ghost]),
            new AdminMenuItemCollection([$child]),
        ]);

        static::assertSame(2, $this->synchronizer($repository, new AdminMenuDefinitionStruct('sw-customer'))->synchronize());
        static::assertSame([[['id' => $child->getId(), 'parentId' => null, 'afterMenuItemId' => null]]], $repository->updates);
        static::assertSame([[['id' => $ghost->getId()]]], $repository->deletes);
    }

    /**
     * @param StaticEntityRepository<AdminMenuItemCollection> $repository
     */
    private function synchronizer(StaticEntityRepository $repository, AdminMenuDefinitionStruct ...$definitions): CoreMenuSynchronizer
    {
        $registry = static::createStub(AdminMenuRouteRegistry::class);
        $registry->method('getCoreDefinitions')->willReturn($definitions);

        return new CoreMenuSynchronizer($repository, $registry, new CoreMenuRowsBuilder());
    }

    private function item(string $key): AdminMenuItemEntity
    {
        $item = new AdminMenuItemEntity();
        $item->assign([
            'id' => (new CoreMenuRowsBuilder())->idFor($key),
            'internalKey' => $key,
            'type' => 'menu',
            'configOverridden' => false,
        ]);

        return $item;
    }
}
