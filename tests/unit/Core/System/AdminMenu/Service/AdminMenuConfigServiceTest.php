<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemRole\AdminMenuItemRoleDefinition;
use Shopwell\Core\System\AdminMenu\Service\AdminMenuConfigService;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AdminMenuConfigService::class)]
class AdminMenuConfigServiceTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testMoveBelowOwnChildIsRejected(): void
    {
        $item = $this->createItem('x');
        $child = $this->createItem('y', parentId: 'x');

        $service = new AdminMenuConfigService(
            $this->createMenuItemRepository([
                new AdminMenuItemCollection([$item]),
                new AdminMenuItemCollection([$child]),
            ]),
            $this->createAclRoleRepository([]),
            $this->createMappingRepository()
        );

        $this->assertRejectsWith(
            AdminMenuConfigService::VIOLATION_MOVE_UNDER_DESCENDANT,
            '/parentId',
            fn () => $service->move($this->context, 'x', 'y', null)
        );
    }

    public function testMovingBelowADeepGroupDescendantIsRejected(): void
    {
        $searches = [new AdminMenuItemCollection([$this->createItem('root')])];
        for ($index = 0; $index < 6; ++$index) {
            $searches[] = new AdminMenuItemCollection([$this->createItem('child-' . $index, parentId: $index === 5 ? 'root' : 'child-' . ($index + 1))]);
        }
        $service = new AdminMenuConfigService($this->createMenuItemRepository($searches), $this->createAclRoleRepository([]), $this->createMappingRepository());
        $this->assertRejectsWith(AdminMenuConfigService::VIOLATION_MOVE_UNDER_DESCENDANT, '/parentId', fn () => $service->move($this->context, 'root', 'child-0', null));
    }

    public function testMoveAfterANonSiblingIsRejected(): void
    {
        $item = $this->createItem('x', parentId: 'level');
        $level = $this->createItem('level');
        $foreignItem = $this->createItem('b', parentId: 'other-level');

        $service = new AdminMenuConfigService(
            $this->createMenuItemRepository([
                new AdminMenuItemCollection([$item]),
                new AdminMenuItemCollection([$level]),
                new AdminMenuItemCollection([$foreignItem]),
            ]),
            $this->createAclRoleRepository([]),
            $this->createMappingRepository()
        );

        $this->assertRejectsWith(
            AdminMenuConfigService::VIOLATION_AFTER_NOT_SIBLING,
            '/afterMenuItemId',
            fn () => $service->move($this->context, 'x', 'level', 'b')
        );
    }

    public function testMoveRepairsTheChainOfBothLevels(): void
    {
        $item = $this->createItem('x', parentId: 'level', afterMenuItemId: 'a');
        $level = $this->createItem('level');
        $targetPredecessor = $this->createItem('b', parentId: 'level');
        $followerOfItem = $this->createItem('f', parentId: 'level', afterMenuItemId: 'x');
        $followerOfTarget = $this->createItem('t', parentId: 'level', afterMenuItemId: 'b');

        $repository = $this->createMenuItemRepository([
            new AdminMenuItemCollection([$item]),
            new AdminMenuItemCollection([$level]),
            new AdminMenuItemCollection([$targetPredecessor]),
            new AdminMenuItemCollection([$followerOfItem]),
            new AdminMenuItemCollection([$followerOfTarget]),
        ]);

        $service = new AdminMenuConfigService($repository, $this->createAclRoleRepository([]), $this->createMappingRepository());

        $service->move($this->context, 'x', 'level', 'b');

        static::assertSame([[
            ['id' => 'f', 'afterMenuItemId' => 'a'],
            ['id' => 't', 'afterMenuItemId' => 'x'],
            ['id' => 'x', 'parentId' => 'level', 'afterMenuItemId' => 'b'],
        ]], $repository->updates);
    }

    public function testMovingToTheCurrentPositionDoesNotRewriteTheFollowingSibling(): void
    {
        $item = $this->createItem('x', afterMenuItemId: 'a');
        $repository = $this->createMenuItemRepository([new AdminMenuItemCollection([$item]), new AdminMenuItemCollection([$item])]);
        $service = new AdminMenuConfigService($repository, $this->createAclRoleRepository([]), $this->createMappingRepository());

        $service->move($this->context, 'x', null, 'a');

        static::assertSame([], $repository->updates);
    }

    public function testPlacingANewRootRepairsTheExistingHead(): void
    {
        $item = $this->createItem('new');
        $head = $this->createItem('existing');
        $repository = $this->createMenuItemRepository([
            new AdminMenuItemCollection([$item]),
            new AdminMenuItemCollection([$item, $head]),
        ]);
        $service = new AdminMenuConfigService($repository, $this->createAclRoleRepository([]), $this->createMappingRepository());

        $service->move($this->context, 'new', null, null);

        static::assertSame([[['id' => 'existing', 'afterMenuItemId' => 'new']]], $repository->updates);
    }

    public function testAssignRolesRejectsUnknownRoles(): void
    {
        $item = $this->createItem('x');

        $service = new AdminMenuConfigService(
            $this->createMenuItemRepository([new AdminMenuItemCollection([$item])]),
            $this->createAclRoleRepository(['role-a']),
            $this->createMappingRepository()
        );

        $this->assertRejectsWith(
            AdminMenuConfigService::VIOLATION_UNKNOWN_ROLE,
            '/roles',
            fn () => $service->assignRoles($this->context, 'x', ['role-a', 'role-b'])
        );
    }

    public function testAssignRolesWritesTheMapping(): void
    {
        $item = $this->createItem('x');

        $menuItemRepository = $this->createMenuItemRepository([new AdminMenuItemCollection([$item])]);
        $service = new AdminMenuConfigService($menuItemRepository, $this->createAclRoleRepository(['role-a', 'role-b']), $this->createMappingRepository());

        $service->assignRoles($this->context, 'x', ['role-b', 'role-a', 'role-b']);

        static::assertSame([[
            ['id' => 'x', 'roles' => [['id' => 'role-b'], ['id' => 'role-a']]],
        ]], $menuItemRepository->updates);
    }

    public function testAssigningNoRolesRemovesExistingMappings(): void
    {
        $item = $this->createItem('x');
        $role = new AclRoleEntity();
        $role->setId('role-a');
        $item->setRoles(new AclRoleCollection([$role]));
        $mappingRepository = $this->createMappingRepository();
        $service = new AdminMenuConfigService(
            $this->createMenuItemRepository([new AdminMenuItemCollection([$item])]),
            $this->createAclRoleRepository([]),
            $mappingRepository
        );

        $service->assignRoles($this->context, 'x', []);

        static::assertSame([[['adminMenuItemId' => 'x', 'aclRoleId' => 'role-a']]], $mappingRepository->deletes);
    }

    /**
     * @return StaticEntityRepository<EntityCollection<Entity>>
     */
    private function createMappingRepository(): StaticEntityRepository
    {
        return new StaticEntityRepository([new EntityCollection()], new AdminMenuItemRoleDefinition());
    }

    /**
     * The rejection is asserted on the violation and not on the exception message, as the
     * exception itself only reports how many violations it carries.
     */
    private function assertRejectsWith(string $expectedCode, string $expectedPropertyPath, callable $action): void
    {
        try {
            $action();
        } catch (WriteConstraintViolationException $exception) {
            $violations = iterator_to_array($exception->getViolations());

            static::assertCount(1, $violations);
            static::assertSame($expectedCode, $violations[0]->getCode());
            static::assertSame($expectedPropertyPath, $violations[0]->getPropertyPath());

            return;
        }

        static::fail(\sprintf('Expected the action to be rejected with the violation "%s".', $expectedCode));
    }

    /**
     * @param list<AdminMenuItemCollection> $searchResults
     *
     * @return StaticEntityRepository<AdminMenuItemCollection>
     */
    private function createMenuItemRepository(array $searchResults): StaticEntityRepository
    {
        return StaticEntityRepository::of(AdminMenuItemCollection::class, $searchResults, new AdminMenuItemDefinition());
    }

    /**
     * @param list<string> $roleIds
     *
     * @return StaticEntityRepository<AclRoleCollection>
     */
    private function createAclRoleRepository(array $roleIds): StaticEntityRepository
    {
        return StaticEntityRepository::of(AclRoleCollection::class, [$roleIds]);
    }

    private function createItem(
        string $id,
        ?string $parentId = null,
        ?string $afterMenuItemId = null,
    ): AdminMenuItemEntity {
        $item = new AdminMenuItemEntity();
        $item->setId($id);
        $item->setType(AdminMenuItemDefinition::TYPE_FOLDER);
        $item->setParentId($parentId);
        $item->setAfterMenuItemId($afterMenuItemId);
        $item->setActive(true);
        $item->setSysLocked(false);
        $item->setConfigOverridden(false);
        $item->setArchived(false);

        return $item;
    }
}
