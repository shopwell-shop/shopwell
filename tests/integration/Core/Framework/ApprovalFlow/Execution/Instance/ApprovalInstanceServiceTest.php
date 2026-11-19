<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalOperationEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalInstanceServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private ApprovalInstanceService $service;

    private string $instanceId;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->service = $container->get(ApprovalInstanceService::class);
        $releaseId = InstanceFixture::publish($container);
        $this->instanceId = InstanceFixture::create($this->connection, $releaseId, ApprovalInstanceStatus::Running);
    }

    public function testSuspendsARunningInstanceAndAppendsItsAuditReceipt(): void
    {
        $commandId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $result = $this->service->suspend($this->instanceId, $commandId, 1, 'Investigate a disputed payment.', $context);

        static::assertSame($this->instanceId, $result->instanceId);
        static::assertSame($commandId, $result->commandId);
        static::assertSame(ApprovalInstanceStatus::Suspended, $result->status);
        static::assertSame(2, $result->aggregateVersion);
        $stored = $this->instance();
        static::assertSame('suspended', $stored['status']);
        static::assertSame(2, (int) $stored['aggregate_version']);
        $receipt = $this->receipt();
        static::assertSame('suspend', $receipt['operation']);
        static::assertSame('running', $receipt['previous_status']);
        static::assertSame('suspended', $receipt['status']);
        static::assertSame(1, (int) $receipt['previous_version']);
        static::assertSame(2, (int) $receipt['aggregate_version']);
        static::assertSame('Investigate a disputed payment.', $receipt['reason']);
        static::assertSame('{"source":"system"}', $receipt['actor_json']);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_instance');
        $entity = $repository->search(new Criteria([$this->instanceId]), $context)->first();
        static::assertInstanceOf(ApprovalInstanceEntity::class, $entity);
        static::assertSame(ApprovalInstanceStatus::Suspended, $entity->getStatus());
        $operationRepository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_operation');
        // Composite IDs follow DAL extraction priority: IdField precedes FkField.
        $criteria = new Criteria([['commandId' => $commandId, 'instanceId' => $this->instanceId]]);
        $criteria->addAssociation('instance.release');
        $operation = $operationRepository->search($criteria, $context)->first();
        static::assertInstanceOf(ApprovalOperationEntity::class, $operation);
        static::assertSame(ApprovalInstanceStatus::Running, $operation->getPreviousStatus());
        static::assertSame(ApprovalInstanceStatus::Suspended, $operation->getStatus());
        static::assertSame($entity->getReleaseId(), $operation->getInstance()?->getRelease()?->getId());
        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
    }

    public function testRepeatedCommandReturnsItsOriginalReceiptWithoutChangingStateAgain(): void
    {
        $commandId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $first = $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', $context);
        $before = $this->instance();

        $repeated = $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', $context);

        static::assertEquals($first, $repeated);
        static::assertSame($before, $this->instance());
        static::assertSame(1, $this->receiptCount());
    }

    public function testOldReceiptDoesNotReturnTheLatestInstanceStateAsItsOriginalResult(): void
    {
        $commandId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $first = $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', $context);
        // A future admitted command may advance the aggregate after the original response was lost.
        $this->connection->update('approval_flow_instance', ['status' => 'terminated', 'aggregate_version' => 3], ['id' => Uuid::fromHexToBytes($this->instanceId)]);

        $repeated = $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', $context);

        static::assertEquals($first, $repeated);
        static::assertSame('terminated', $this->instance()['status']);
        static::assertSame(3, (int) $this->instance()['aggregate_version']);
    }

    public function testReusingACommandWithDifferentContentFailsWithoutASecondWrite(): void
    {
        $commandId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $this->service->suspend($this->instanceId, $commandId, 1, 'Original reason.', $context);
        $before = $this->instance();

        try {
            $this->service->suspend($this->instanceId, $commandId, 1, 'Different reason.', $context);
            static::fail('A command identity cannot be reused for another request.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INSTANCE_COMMAND_CONFLICT, $exception->getErrorCode());
        }
        static::assertSame($before, $this->instance());
        static::assertSame(1, $this->receiptCount());
    }

    public function testReusingACommandWithAnotherExpectedVersionIsAContentConflict(): void
    {
        $commandId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', $context);
        $this->expectExceptionObject(ApprovalFlowException::instanceCommandConflict($this->instanceId, $commandId));

        $this->service->suspend($this->instanceId, $commandId, 2, 'Review required.', $context);
    }

    public function testDifferentActorsCannotReuseEachOthersReceipts(): void
    {
        $commandId = Uuid::randomHex();
        $first = new AdminApiSource(Uuid::randomHex());
        $first->setPermissions(['approval_flow_instance:manage']);
        $second = new AdminApiSource(Uuid::randomHex());
        $second->setPermissions(['approval_flow_instance:manage']);
        $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', Context::createDefaultContext($first));
        $this->expectExceptionObject(ApprovalFlowException::instanceCommandConflict($this->instanceId, $commandId));

        $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', Context::createDefaultContext($second));
    }

    public function testPermissionIsRecheckedBeforeReturningAnExistingReceipt(): void
    {
        $commandId = Uuid::randomHex();
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_instance:manage']);
        $context = Context::createDefaultContext($source);
        $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', $context);
        $source->setPermissions([]);
        $this->expectExceptionObject(new MissingPrivilegeException(['approval_flow_instance:manage']));

        $this->service->suspend($this->instanceId, $commandId, 1, 'Review required.', $context);
    }

    public function testManagementPrivilegePrecedesInputValidationAndInstanceLookup(): void
    {
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));
        $this->expectExceptionObject(new MissingPrivilegeException(['approval_flow_instance:manage']));

        $this->service->suspend(Uuid::randomHex(), Uuid::randomHex(), 0, '', $context);
    }

    public function testMissingInstanceReturnsAnExplicitError(): void
    {
        $id = Uuid::randomHex();
        $this->expectExceptionObject(ApprovalFlowException::instanceNotFound($id));

        $this->service->suspend($id, Uuid::randomHex(), 1, 'Review required.', Context::createDefaultContext());
    }

    public function testStaleVersionLeavesStateAndAuditUnchanged(): void
    {
        $before = $this->instance();
        try {
            $this->service->suspend($this->instanceId, Uuid::randomHex(), 2, 'Review required.', Context::createDefaultContext());
            static::fail('A stale structural command must not change the aggregate.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INSTANCE_VERSION_CONFLICT, $exception->getErrorCode());
            static::assertSame(1, $exception->getParameters()['actualVersion']);
        }
        static::assertSame($before, $this->instance());
        static::assertSame(0, $this->receiptCount());
    }

    #[DataProvider('nonRunningStates')]
    public function testOnlyARunningInstanceCanBeSuspended(string $status): void
    {
        $this->connection->update('approval_flow_instance', ['status' => $status], ['id' => Uuid::fromHexToBytes($this->instanceId)]);
        try {
            $this->service->suspend($this->instanceId, Uuid::randomHex(), 1, 'Review required.', Context::createDefaultContext());
            static::fail('A new suspension must not alter a non-running instance.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_INSTANCE_TRANSITION, $exception->getErrorCode());
            static::assertSame($status, $exception->getParameters()['status']);
        }
        static::assertSame($status, $this->instance()['status']);
        static::assertSame(0, $this->receiptCount());
    }

    public static function nonRunningStates(): iterable
    {
        yield 'draft' => ['draft'];
        yield 'suspended' => ['suspended'];
        yield 'completed' => ['completed'];
        yield 'terminated' => ['terminated'];
        yield 'withdrawn' => ['withdrawn'];
    }

    #[DataProvider('invalidCommands')]
    public function testMalformedCommandsNeverWrite(int $version, string $reason): void
    {
        try {
            $this->service->suspend($this->instanceId, Uuid::randomHex(), $version, $reason, Context::createDefaultContext());
            static::fail('Malformed commands must be rejected.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_INSTANCE_COMMAND, $exception->getErrorCode());
        }
        static::assertSame('running', $this->instance()['status']);
        static::assertSame(0, $this->receiptCount());
    }

    public static function invalidCommands(): iterable
    {
        yield 'zero version' => [0, 'Review required.'];
        yield 'blank reason' => [1, " \n\t"];
        yield 'overlong reason' => [1, str_repeat('审', 1001)];
    }

    public function testOperationFailureRollsBackTheInstanceAndRestoresTheOriginalContext(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_instance:manage']);
        $context = Context::createDefaultContext($source);
        $before = $this->instance();
        $cause = new \RuntimeException('Audit persistence failed.');
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_operation.written', static function () use ($cause): void {
            throw $cause;
        });

        try {
            $this->service->suspend($this->instanceId, Uuid::randomHex(), 1, 'Review required.', $context);
            static::fail('Instance state and its audit must commit together.');
        } catch (\RuntimeException $exception) {
            static::assertSame($cause, $exception);
        }
        static::assertSame($before, $this->instance());
        static::assertSame(0, $this->receiptCount());
        static::assertSame(Context::USER_SCOPE, $context->getScope());
    }

    public function testOriginalAdminIdentityAndContextReachTheWriteBoundary(): void
    {
        $userId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();
        $source = new AdminApiSource($userId, $integrationId);
        $source->setPermissions(['approval_flow_instance:manage']);
        $context = Context::createDefaultContext($source);
        $observed = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_operation.written', static function (EntityWrittenEvent $event) use (&$observed): void {
            $observed = $event->getContext();
        });

        $this->service->suspend($this->instanceId, Uuid::randomHex(), 1, '核验收款信息。', $context);

        static::assertSame($context, $observed);
        static::assertSame(['source' => 'admin-api', 'userId' => $userId, 'integrationId' => $integrationId], json_decode($this->receipt()['actor_json'], associative: true, flags: \JSON_THROW_ON_ERROR));
        static::assertSame('核验收款信息。', $this->receipt()['reason']);
        static::assertSame(Context::USER_SCOPE, $context->getScope());
    }

    public function testWriteGatewayCallbackCannotBorrowTheCommandWritePermission(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_instance');
        $registered = false;
        $attempted = false;
        $writeScope = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityWriteEvent::class, function (EntityWriteEvent $event) use ($repository, &$registered, &$attempted, &$writeScope): void {
            if ($registered || $event->getCommandsForEntity('approval_flow_instance') === []) {
                return;
            }
            $registered = true;
            $event->addSuccess(function () use ($repository, $event, &$attempted, &$writeScope): void {
                $attempted = true;
                $writeScope = $event->getContext()->getScope();
                $repository->update([['id' => $this->instanceId, 'status' => 'running']], $event->getContext());
            });
        });
        $context = Context::createDefaultContext();
        $before = $this->instance();

        try {
            $this->service->suspend($this->instanceId, Uuid::randomHex(), 1, 'Review required.', $context);
            static::fail('A write-gateway callback must not inherit command write permission.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::UNCONTROLLED_INSTANCE_WRITE, $exception->getErrorCode());
        }
        static::assertTrue($attempted);
        static::assertSame(Context::SYSTEM_SCOPE, $writeScope);
        static::assertSame($before, $this->instance());
        static::assertSame(0, $this->receiptCount());
    }

    public function testWriteEventCannotChangeAnAdmittedCommandPayload(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityWriteEvent::class, static function (EntityWriteEvent $event): void {
            foreach ($event->getCommandsForEntity('approval_flow_instance') as $command) {
                $command->addPayload('status', 'running');
            }
        });
        $before = $this->instance();

        try {
            $this->service->suspend($this->instanceId, Uuid::randomHex(), 1, 'Review required.', Context::createDefaultContext());
            static::fail('An event must not change the admitted transition before its write.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::UNCONTROLLED_INSTANCE_WRITE, $exception->getErrorCode());
        }
        static::assertSame($before, $this->instance());
        static::assertSame(0, $this->receiptCount());
    }

    public function testWrittenEventCannotBorrowTheCommandWritePermission(): void
    {
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        $repository = $registry->getRepository('approval_flow_instance');
        $attempted = false;
        $writeScope = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_instance.written', function (EntityWrittenEvent $event) use ($repository, &$attempted, &$writeScope): void {
            if ($attempted) {
                return;
            }
            $attempted = true;
            $writeScope = $event->getContext()->getScope();
            $repository->update([['id' => $this->instanceId, 'status' => 'running']], $event->getContext());
        });
        $context = Context::createDefaultContext();
        $before = $this->instance();

        try {
            $this->service->suspend($this->instanceId, Uuid::randomHex(), 1, 'Review required.', $context);
            static::fail('An event listener must not inherit the command write permission.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::UNCONTROLLED_INSTANCE_WRITE, $exception->getErrorCode());
        }
        static::assertTrue($attempted);
        static::assertSame(Context::SYSTEM_SCOPE, $writeScope);
        static::assertSame($before, $this->instance());
        static::assertSame(0, $this->receiptCount());
    }

    /**
     * @return array<string, mixed>
     */
    private function instance(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]);
        static::assertIsArray($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function receipt(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM approval_flow_operation WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]);
        static::assertIsArray($row);

        return $row;
    }

    private function receiptCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]);
    }
}
