<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Permission;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\AclRoleLoader;
use Shopwell\Core\Framework\Api\Acl\AclRoles;
use Shopwell\Core\Framework\Api\Acl\IdentityRoleMap;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRoleInstaller;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRolesEvent;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\AdminMenuXmlReader;
use Shopwell\Core\System\AdminMenu\CoreAdminMenuProvider;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuRowsBuilder;
use Shopwell\Core\System\AdminMenu\Service\VisibleMenuTreeBuilder;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowDefaultRoleInstaller::class)]
class WorkflowDefaultRoleInstallerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<AclRoleCollection>&MockObject
     */
    private StaticEntityRepository $failingRepository;

    /**
     * @var StaticEntityRepository<AdminMenuItemCollection>
     */
    private StaticEntityRepository $menuRepository;

    protected function setUp(): void
    {
        $this->menuRepository = StaticEntityRepository::of(AdminMenuItemCollection::class, [[], []]);
        $this->failingRepository = $this->getMockBuilder(StaticEntityRepository::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['create'])
            ->getMock();
    }

    public function testRoleDefaultsUseTheSelectedLanguage(): void
    {
        $repository = new StaticEntityRepository([new AclRoleCollection(), new AclRoleCollection()]);
        $installer = new WorkflowDefaultRoleInstaller($repository, new EventDispatcher(), $this->menuRepository, ApprovalRegistryFixture::translator('en'));
        $installer->install(Context::createDefaultContext());
        $roles = $repository->getPayloads(StaticEntityRepository::CREATE);

        static::assertSame('Workflow manager', $roles[0]['name']);
        static::assertSame('Manage workflow definitions, review drafts and publish workflows.', $roles[0]['description']);
        static::assertSame('Workflow participant', $roles[1]['name']);
    }

    public function testCreatesDefaultRolesWithStableIdsAndNoUserAssignments(): void
    {
        $repository = new StaticEntityRepository([new AclRoleCollection(), new AclRoleCollection()]);
        $installer = new WorkflowDefaultRoleInstaller($repository, new EventDispatcher(), $this->menuRepository, ApprovalRegistryFixture::translator('zh'));

        static::assertSame(2, $installer->install(Context::createDefaultContext()));

        static::assertSame([
            [
                'id' => Uuid::fromStringToHex('shopwell.workflow.role.manager'),
                'name' => '流程管理员',
                'description' => '管理流程定义、查看草稿并发布流程。',
                'privileges' => [
                    'workflow.viewer', 'workflow.creator', 'workflow.editor', 'workflow.deleter', 'workflow.publisher',
                    'workflow:read', 'workflow:create', 'workflow:update', 'workflow:delete', 'workflow:publish', 'workflow_draft:read', 'position:read', 'acl_role:read', 'user:read', 'organization:read', 'organization_unit:read',
                ],
                'adminMenuItems' => [],
            ],
            [
                'id' => Uuid::fromStringToHex('shopwell.workflow.role.participant'),
                'name' => '流程参与者',
                'description' => '发起流程、处理分配给自己的审批任务并查看参与记录。',
                'privileges' => (new WorkflowDefaultRolesEvent())->getPrivileges(WorkflowDefaultRolesEvent::PARTICIPANT),
                'adminMenuItems' => [],
            ],
        ], $repository->getPayloads(StaticEntityRepository::CREATE));
        static::assertSame([], $repository->updates);
        static::assertSame([], $repository->upserts);
    }

    public function testRepeatedInstallPreservesCustomizedRoles(): void
    {
        $manager = new AclRoleEntity();
        $manager->setId(Uuid::fromStringToHex('shopwell.workflow.role.manager'));
        $manager->setName('自定义管理员');
        $manager->setPrivileges([]);
        $participant = new AclRoleEntity();
        $participant->setId(Uuid::fromStringToHex('shopwell.workflow.role.participant'));
        $participant->setPrivileges(['example:custom']);
        $repository = new StaticEntityRepository([
            new AclRoleCollection(), new AclRoleCollection(),
            new AclRoleCollection([$manager]), new AclRoleCollection([$participant]),
        ]);
        $dispatcher = new EventDispatcher();
        $installer = new WorkflowDefaultRoleInstaller($repository, $dispatcher, $this->menuRepository, ApprovalRegistryFixture::translator('zh'));

        static::assertSame(2, $installer->install(Context::createDefaultContext()));
        $dispatcher->addListener(WorkflowDefaultRolesEvent::class, static function (WorkflowDefaultRolesEvent $event): void {
            $event->addPrivileges(WorkflowDefaultRolesEvent::MANAGER, ['example:new']);
        });

        static::assertSame(0, $installer->install(Context::createDefaultContext()));
        static::assertCount(2, $repository->getPayloads(StaticEntityRepository::CREATE));
        static::assertSame([], $repository->updates);
        static::assertSame([], $repository->upserts);
        static::assertSame('自定义管理员', $manager->getName());
        static::assertSame([], $manager->getPrivileges());
        static::assertSame(['example:custom'], $participant->getPrivileges());
        static::assertCount(2, $this->menuRepository->searchedCriteria);
    }

    public function testInstallsOnlyMissingRoleWithExtensionPrivileges(): void
    {
        $managerId = Uuid::fromStringToHex('shopwell.workflow.role.manager');
        $repository = StaticEntityRepository::of(AclRoleCollection::class, [[$managerId], []]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkflowDefaultRolesEvent::class, static function (WorkflowDefaultRolesEvent $event): void {
            $event->addPrivileges(WorkflowDefaultRolesEvent::PARTICIPANT, ['example:participate']);
        });
        $installer = new WorkflowDefaultRoleInstaller($repository, $dispatcher, $this->menuRepository, ApprovalRegistryFixture::translator('zh'));

        static::assertSame(1, $installer->install(Context::createDefaultContext()));

        $payloads = $repository->getPayloads(StaticEntityRepository::CREATE);
        static::assertCount(1, $payloads);
        static::assertSame(Uuid::fromStringToHex('shopwell.workflow.role.participant'), $payloads[0]['id']);
        static::assertSame([...(new WorkflowDefaultRolesEvent())->getPrivileges(WorkflowDefaultRolesEvent::PARTICIPANT), 'example:participate'], $payloads[0]['privileges']);
        static::assertSame([$managerId], $repository->searchedCriteria[0]->getIds());
    }

    public function testConcurrentInsertIsSkippedOnlyAfterConfirmingTheRoleExists(): void
    {
        $managerId = Uuid::fromStringToHex('shopwell.workflow.role.manager');
        $this->failingRepository->searches = [
            [], [$managerId], [Uuid::fromStringToHex('shopwell.workflow.role.participant')],
        ];
        $exception = new UniqueConstraintViolationException(static::createStub(DriverException::class), null);
        $this->failingRepository->expects($this->once())->method('create')->willThrowException($exception);
        $installer = new WorkflowDefaultRoleInstaller($this->failingRepository, new EventDispatcher(), $this->menuRepository, ApprovalRegistryFixture::translator('zh'));

        static::assertSame(0, $installer->install(Context::createDefaultContext()));
        static::assertSame([$managerId], $this->failingRepository->searchedCriteria[1]->getIds());
        static::assertSame([], $this->failingRepository->updates);
        static::assertSame([], $this->failingRepository->upserts);
    }

    public function testWriteFailureIsNotSwallowedWhenTheRoleDoesNotExist(): void
    {
        $this->failingRepository->searches = [[], []];
        $exception = new WriteTypeIntendException(new AclRoleDefinition(), InsertCommand::class, UpdateCommand::class);
        $this->failingRepository->expects($this->once())->method('create')->willThrowException($exception);
        $installer = new WorkflowDefaultRoleInstaller($this->failingRepository, new EventDispatcher(), $this->menuRepository, ApprovalRegistryFixture::translator('zh'));

        $this->expectExceptionObject($exception);

        $installer->install(Context::createDefaultContext());
    }

    public function testCreatedRolesHaveReachableNavigationWithoutSelectingTheirParents(): void
    {
        $provider = new CoreAdminMenuProvider((new AdminMenuXmlReader())->read(CoreAdminMenuProvider::MENU_FILE));
        $definitions = array_column($provider->getDefinitions(), null, 'internalKey');
        $rowsBuilder = new CoreMenuRowsBuilder();
        $menuItems = new AdminMenuItemCollection();
        foreach ($rowsBuilder->build($provider->getDefinitions()) as $row) {
            $item = new AdminMenuItemEntity();
            $item->assign([...$row->toArray(), 'active' => true, 'archived' => false, 'sysLocked' => true, 'configOverridden' => false]);
            $menuItems->add($item);
        }
        $designId = $rowsBuilder->idFor('sw-workflow-design');
        $tasksId = $rowsBuilder->idFor('sw-workflow-tasks');
        $this->menuRepository->searches = [[$designId], [$tasksId]];
        $repository = new StaticEntityRepository([new AclRoleCollection(), new AclRoleCollection()]);
        $installer = new WorkflowDefaultRoleInstaller($repository, new EventDispatcher(), $this->menuRepository, ApprovalRegistryFixture::translator('zh'));

        static::assertSame(2, $installer->install(Context::createDefaultContext()));

        $payloads = $repository->getPayloads(StaticEntityRepository::CREATE);
        static::assertSame([['id' => $designId]], $payloads[0]['adminMenuItems']);
        static::assertSame([['id' => $tasksId]], $payloads[1]['adminMenuItems']);
        static::assertEquals([new EqualsAnyFilter('internalKey', ['sw-workflow-design'])], $this->menuRepository->searchedCriteria[0]->getFilters());
        static::assertEquals([new EqualsAnyFilter('internalKey', ['sw-workflow-tasks'])], $this->menuRepository->searchedCriteria[1]->getFilters());

        foreach ([['sw-workflow-design'], ['sw-workflow-tasks']] as $index => $expectedLeaves) {
            $role = new AclRoleEntity();
            $role->setId(Uuid::randomHex());
            $selected = $payloads[$index]['adminMenuItems'];
            static::assertIsArray($selected);
            $selectedIds = array_column($selected, 'id');
            $role->setAdminMenuItems($menuItems->filter(static fn (AdminMenuItemEntity $item): bool => \in_array($item->getId(), $selectedIds, true)));
            $userId = Uuid::randomHex();
            $roleLoader = static::createStub(AclRoleLoader::class);
            $roleLoader->method('getRoles')->willReturn(new IdentityRoleMap([$userId => new AclRoles(false, [$role->getId()])], []));
            $treeBuilder = new VisibleMenuTreeBuilder(
                new StaticEntityRepository([$menuItems]),
                new AdminMenuRouteRegistry(new MenuDefinitionCollector([$provider])),
                $roleLoader,
                new StaticEntityRepository([new AclRoleCollection([$role])]),
                new EventDispatcher(),
            );

            $tree = $treeBuilder->build(new Context(new AdminApiSource($userId)));

            static::assertCount(1, $tree);
            static::assertNull($definitions['sw-workflow']->parent);
            static::assertSame('sw-workflow', $tree[0]['internalKey']);
            static::assertSame($expectedLeaves, array_column($tree[0]['children'], 'internalKey'));
        }
    }

    public function testExtensionMenuSelectionOnlyBindsExistingMenuRows(): void
    {
        $repository = StaticEntityRepository::of(AclRoleCollection::class, [[Uuid::fromStringToHex('shopwell.workflow.role.manager')], []]);
        $releasesId = Uuid::randomHex();
        $inboxId = Uuid::randomHex();
        $this->menuRepository->searches = [[$releasesId, $inboxId]];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkflowDefaultRolesEvent::class, static function (WorkflowDefaultRolesEvent $event): void {
            $event->addMenuKeys(WorkflowDefaultRolesEvent::PARTICIPANT, ['example-inbox', 'example-uninstalled-menu']);
        });
        $installer = new WorkflowDefaultRoleInstaller($repository, $dispatcher, $this->menuRepository, ApprovalRegistryFixture::translator('zh'));

        static::assertSame(1, $installer->install(Context::createDefaultContext()));

        static::assertEquals([new EqualsAnyFilter('internalKey', ['sw-workflow-tasks', 'example-inbox', 'example-uninstalled-menu'])], $this->menuRepository->searchedCriteria[0]->getFilters());
        static::assertSame([['id' => $releasesId], ['id' => $inboxId]], $repository->getPayloads(StaticEntityRepository::CREATE)[0]['adminMenuItems']);
    }
}
