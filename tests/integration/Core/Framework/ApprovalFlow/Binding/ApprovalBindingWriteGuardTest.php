<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Binding;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Sync\SyncOperation;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingStore;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalBindingWriteGuardTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private string $bindingId;

    private string $headId;

    private string $releaseId;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->releaseId = InstanceFixture::publish($container);
        $specification = new ApprovalBusinessTypeSpecification('payment:transfer', '1.0.0', 'transfer', 'test:payment', '{"$id":"payment:transfer","type":"object"}');
        $store = $container->get(ApprovalBindingStore::class);
        $this->bindingId = $store->save($specification, $this->releaseId, 0, Context::createDefaultContext())->getId();
        $this->headId = $store->head('payment:transfer', Context::createDefaultContext())->getId();
    }

    #[DataProvider('immutableWrites')]
    public function testBindingHistoryCannotBeChangedEvenInsideTheWriteScope(string $method, bool $controlled): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_binding');
        $data = ['id' => $this->bindingId];
        if ($method === 'update') {
            $data['formSchema'] = '{}';
        }
        $context = Context::createDefaultContext();
        $write = static function (Context $writeContext) use ($repository, $data, $method): void {
            if ($method === 'update') {
                $repository->update([$data], $writeContext);

                return;
            }
            $repository->delete([$data], $writeContext);
        };
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledBindingWrite());

        if ($controlled) {
            static::getContainer()->get(ApprovalBindingWriteGuard::class)->write($context, $write);

            return;
        }
        $write($context);
    }

    public static function immutableWrites(): iterable
    {
        yield 'ordinary update' => ['update', false];
        yield 'ordinary delete' => ['delete', false];
        yield 'controlled update' => ['update', true];
        yield 'controlled delete' => ['delete', true];
    }

    public function testOrdinaryDalCannotActivateABinding(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_binding_head');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledBindingWrite());

        $repository->update([['id' => $this->headId, 'activeRevision' => 1, 'version' => 1]], Context::createDefaultContext());
    }

    public function testSyncCannotChangeTheActiveBinding(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledBindingWrite());

        static::getContainer()->get(EntityWriter::class)->sync([
            new SyncOperation('binding', 'approval_flow_binding_head', SyncOperation::ACTION_UPSERT, [
                ['id' => $this->headId, 'activeRevision' => 1, 'version' => 1],
            ]),
        ], WriteContext::createFromContext(Context::createDefaultContext()));
    }

    public function testKnowingTheScopeNameDoesNotAuthorizeAWrite(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_binding_head');
        $context = Context::createDefaultContext();
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledBindingWrite());

        $context->scope(ApprovalBindingWriteGuard::WRITE_SCOPE, fn (Context $writeContext) => $repository->update([[
            'id' => $this->headId, 'activeRevision' => 1, 'version' => 1,
        ]], $writeContext));
    }

    public function testAListenerCannotReuseTheOuterWritePermission(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_binding_head');
        $headId = $this->headId;
        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityWriteEvent::class, static function (EntityWriteEvent $event) use ($repository, $headId): void {
            if ($event->getCommands()[0]->getEntityName() !== 'approval_flow_binding') {
                return;
            }
            $repository->update([['id' => $headId, 'activeRevision' => 1, 'version' => 1]], $event->getContext());
        });
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledBindingWrite());

        $specification = new ApprovalBusinessTypeSpecification('payment:transfer', '1.0.0', 'transfer', 'test:payment', '{"$id":"payment:transfer","type":"object"}');
        static::getContainer()->get(ApprovalBindingStore::class)->save($specification, $this->releaseId, 1, Context::createDefaultContext());
    }

    public function testAnEventCannotChangeTheAdmittedPayload(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityWriteEvent::class, static function (EntityWriteEvent $event): void {
            foreach ($event->getCommands() as $command) {
                if ($command->getEntityName() === 'approval_flow_binding') {
                    $command->addPayload('business_version', '9.0.0');
                }
            }
        });
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledBindingWrite());

        $specification = new ApprovalBusinessTypeSpecification('payment:transfer', '1.0.0', 'transfer', 'test:payment', '{"$id":"payment:transfer","type":"object"}');
        static::getContainer()->get(ApprovalBindingStore::class)->save($specification, $this->releaseId, 1, Context::createDefaultContext());
    }

    public function testTheWriteScopeCannotRenameTheBusinessType(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_binding_head');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledBindingWrite());

        static::getContainer()->get(ApprovalBindingWriteGuard::class)->write(Context::createDefaultContext(), function (Context $context) use ($repository): void {
            $repository->update([['id' => $this->headId, 'businessType' => 'payment:other']], $context);
        });
    }
}
