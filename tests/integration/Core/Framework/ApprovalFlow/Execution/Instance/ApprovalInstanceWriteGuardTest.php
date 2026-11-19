<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Sync\SyncOperation;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalInstanceWriteGuardTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private string $instanceId;

    private string $releaseId;

    private string $commandId;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->releaseId = InstanceFixture::publish($container);
        $this->instanceId = InstanceFixture::create($container->get(Connection::class), $this->releaseId, ApprovalInstanceStatus::Running);
        $this->commandId = Uuid::randomHex();
        $container->get(ApprovalInstanceService::class)->suspend($this->instanceId, $this->commandId, 1, 'Review required.', Context::createDefaultContext());
    }

    #[DataProvider('instanceWrites')]
    public function testOrdinaryDalAndEvenTheCommandScopeCannotCreateOrDeleteInstances(string $method, bool $controlled): void
    {
        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_instance');
        $data = $method === 'create'
            ? [['id' => Uuid::randomHex(), 'releaseId' => $this->releaseId, 'status' => 'running', 'aggregateVersion' => 1]]
            : [['id' => $this->instanceId]];
        $write = function (Context $writeContext) use ($method, $repository, $data): void {
            if ($method === 'create') {
                $repository->create($data, $writeContext);

                return;
            }
            $repository->delete($data, $writeContext);
        };
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        if ($controlled) {
            static::getContainer()->get(ApprovalInstanceWriteGuard::class)->write($context, $write);

            return;
        }
        $write($context);
    }

    public static function instanceWrites(): iterable
    {
        yield 'ordinary create' => ['create', false];
        yield 'ordinary delete' => ['delete', false];
        yield 'command scope create' => ['create', true];
        yield 'command scope delete' => ['delete', true];
    }

    public function testOrdinaryDalCannotSetAnArbitraryInstanceStatus(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_instance');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        $repository->update([['id' => $this->instanceId, 'status' => 'completed']], Context::createDefaultContext());
    }

    public function testSyncCannotBypassTheInstanceCommandBoundary(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        static::getContainer()->get(EntityWriter::class)->sync([
            new SyncOperation('state', 'approval_flow_instance', SyncOperation::ACTION_UPSERT, [
                ['id' => $this->instanceId, 'status' => 'running', 'aggregateVersion' => 1],
            ]),
        ], WriteContext::createFromContext(Context::createDefaultContext()));
    }

    public function testTheCommandScopeCannotRewriteTheReleaseBinding(): void
    {
        $otherRelease = InstanceFixture::publish(static::getContainer());
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_instance');
        $context = Context::createDefaultContext();
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        static::getContainer()->get(ApprovalInstanceWriteGuard::class)->write($context, function (Context $writeContext) use ($repository, $otherRelease): void {
            $repository->update([['id' => $this->instanceId, 'releaseId' => $otherRelease]], $writeContext);
        });
    }

    #[DataProvider('operationWrites')]
    public function testAuditReceiptsCannotBeRewrittenOrDeleted(string $method, bool $controlled): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_operation');
        $data = ['instanceId' => $this->instanceId, 'commandId' => $this->commandId];
        if ($method === 'update') {
            $data['reason'] = 'Rewritten history.';
        }
        $context = Context::createDefaultContext();
        $write = function (Context $writeContext) use ($method, $repository, $data): void {
            if ($method === 'update') {
                $repository->update([$data], $writeContext);

                return;
            }
            $repository->delete([$data], $writeContext);
        };
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        if ($controlled) {
            static::getContainer()->get(ApprovalInstanceWriteGuard::class)->write($context, $write);

            return;
        }
        $write($context);
    }

    public static function operationWrites(): iterable
    {
        yield 'ordinary update' => ['update', false];
        yield 'ordinary delete' => ['delete', false];
        yield 'command scope update' => ['update', true];
        yield 'command scope delete' => ['delete', true];
    }

    public function testOrdinaryDalCannotForgeAnAuditReceipt(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_operation');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        $repository->create([[
            'instanceId' => $this->instanceId,
            'commandId' => Uuid::randomHex(),
            'operation' => 'suspend',
            'requestHash' => str_repeat('a', 64),
            'actorJson' => '{"source":"system"}',
            'reason' => 'Forged history.',
            'previousStatus' => 'running',
            'status' => 'suspended',
            'previousVersion' => 2,
            'aggregateVersion' => 3,
        ]], Context::createDefaultContext());
    }

    public function testNestedOperationAssociationCannotRewriteAnInstance(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        static::getContainer()->get(EntityWriter::class)->sync([
            new SyncOperation('receipt', 'approval_flow_operation', SyncOperation::ACTION_UPSERT, [[
                'instanceId' => $this->instanceId,
                'commandId' => $this->commandId,
                'instance' => ['id' => $this->instanceId, 'status' => 'running'],
            ]]),
        ], WriteContext::createFromContext(Context::createDefaultContext()));
    }
}
