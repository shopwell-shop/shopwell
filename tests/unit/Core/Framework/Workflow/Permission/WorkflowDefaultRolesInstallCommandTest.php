<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Permission;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRoleInstaller;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRolesInstallCommand;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowDefaultRolesInstallCommand::class)]
class WorkflowDefaultRolesInstallCommandTest extends TestCase
{
    public function testReportsCreatedCountAndLeavesExistingRolesAlone(): void
    {
        $repository = StaticEntityRepository::of(AclRoleCollection::class, [
            [], [],
            [Uuid::fromStringToHex('shopwell.workflow.role.manager')],
            [Uuid::fromStringToHex('shopwell.workflow.role.participant')],
        ]);
        $menuSynchronizer = $this->createMock(CoreMenuSynchronizer::class);
        $menuSynchronizer->expects($this->exactly(2))->method('synchronize')->willReturn(0);
        $command = new WorkflowDefaultRolesInstallCommand(
            new WorkflowDefaultRoleInstaller($repository, new EventDispatcher(), StaticEntityRepository::of(AdminMenuItemCollection::class, [[], []]), ApprovalRegistryFixture::translator()),
            $menuSynchronizer,
        );
        $tester = new CommandTester($command);

        static::assertSame('workflow:roles:install', $command->getName());
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('Created 2 default workflow role(s).', $tester->getDisplay());

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('Created 0 default workflow role(s).', $tester->getDisplay());
        static::assertCount(2, $repository->getPayloads(StaticEntityRepository::CREATE));
        static::assertSame([], $repository->updates);
        static::assertSame([], $repository->upserts);
    }

    public function testMenuSynchronizationCompletesBeforeRolesAreInstalled(): void
    {
        $synchronized = false;
        $menuSynchronizer = $this->createMock(CoreMenuSynchronizer::class);
        $menuSynchronizer->expects($this->once())->method('synchronize')->willReturnCallback(static function () use (&$synchronized): int {
            $synchronized = true;

            return 1;
        });
        $observedSynchronization = false;
        $installer = $this->createMock(WorkflowDefaultRoleInstaller::class);
        $installer->expects($this->once())->method('install')->willReturnCallback(static function () use (&$observedSynchronization, &$synchronized): int {
            $observedSynchronization = $synchronized;

            return 2;
        });
        $tester = new CommandTester(new WorkflowDefaultRolesInstallCommand($installer, $menuSynchronizer));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        static::assertTrue($observedSynchronization);
    }
}
