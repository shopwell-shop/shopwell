<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Binding;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingStore;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingValidationEvent;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalBindingServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private ApprovalBindingService $bindings;

    private ApprovalBusinessTypeSpecification $businessType;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-binding-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $this->runtime = ApprovalRuntimeFixture::container(static::getContainer(), $manifest);
        $this->businessType = new ApprovalBusinessTypeSpecification(
            'payment:transfer',
            '1.0.0',
            'transfer.label',
            'test:payment',
            '{"$id":"payment:transfer","type":"object","properties":{"amount":{"type":"string"}}}',
        );
        $this->bindings = $this->service($this->businessType);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testOperationsUseDalWritePermissionsAndPreserveTheCallerContext(): void
    {
        $source = new AdminApiSource(userId: null, integrationId: Uuid::randomHex());
        $source->setPermissions(['approval_flow_binding:create']);
        $context = Context::createDefaultContext($source);
        $observed = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_binding.written', static function (EntityWrittenEvent $event) use (&$observed): void {
            $observed[] = $event->getContext();
        });
        $binding = $this->bindings->save('payment:transfer', '1.0.0', $this->publish(), 0, $context);
        $head = $this->bindings->activate('payment:transfer', 1, 0, $context);

        static::assertSame([$context], $observed);
        static::assertNull($binding->getCreatedById());
        static::assertSame(1, $head->getVersion());
        static::assertSame($binding->getId(), $this->bindings->read('payment:transfer', 1, $context)->getId());
        static::assertSame($binding->getId(), $this->bindings->active('payment:transfer', $context)->getId());
        static::assertSame($head->getId(), $this->bindings->head('payment:transfer', $context)->getId());
        static::assertSame(Context::USER_SCOPE, $context->getScope());
    }

    public function testDalRejectsMissingWritePermissionAndRollsBackTheInitialization(): void
    {
        $releaseId = $this->publish();
        $context = Context::createDefaultContext(new AdminApiSource(userId: null, integrationId: Uuid::randomHex()));
        try {
            $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $context);
            static::fail('The existing DAL ACL must still reject an unauthorized write.');
        } catch (MissingPrivilegeException $exception) {
            static::assertSame((new MissingPrivilegeException(['approval_flow_binding:create']))->getMessage(), $exception->getMessage());
        }
        $connection = static::getContainer()->get(Connection::class);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_binding_head'));
        static::assertSame(Context::USER_SCOPE, $context->getScope());
    }

    public function testSavingFreezesTheBusinessFormWithoutActivatingTheBinding(): void
    {
        $context = Context::createDefaultContext();
        $releaseId = $this->publish();
        $binding = $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $context);

        static::assertSame(1, $binding->getRevision());
        static::assertSame('1.0.0', $binding->getBusinessVersion());
        static::assertSame($releaseId, $binding->getReleaseId());
        static::assertSame($this->businessType->formSchema, $binding->getFormSchema());
        static::assertSame($this->businessType->formSchemaHash, $binding->getFormSchemaHash());
        static::assertSame($this->businessType->formSchemaId, $binding->getFormSchemaId());
        $head = $this->bindings->head('payment:transfer', $context);
        static::assertSame(1, $head->getLatestRevision());
        static::assertSame(0, $head->getVersion());
        static::assertNull($head->getActiveRevision());
        static::assertSame($binding->getId(), $this->bindings->read('payment:transfer', 1, $context)->getId());
        $this->expectExceptionObject(ApprovalFlowException::activeBindingNotFound('payment:transfer'));

        $this->bindings->active('payment:transfer', $context);
    }

    public function testActivationSwitchesNewLookupsAndCanReturnToAnOlderRevision(): void
    {
        $context = Context::createDefaultContext();
        $first = $this->bindings->save('payment:transfer', '1.0.0', $this->publish(), 0, $context);
        $head = $this->bindings->activate('payment:transfer', 1, 0, $context);
        static::assertSame(1, $head->getVersion());
        static::assertSame($first->getId(), $this->bindings->active('payment:transfer', $context)->getId());
        $second = $this->bindings->save('payment:transfer', '1.0.0', $this->publish('Changed transfer process'), 1, $context);
        static::assertSame(2, $second->getRevision());
        static::assertSame(1, $this->bindings->head('payment:transfer', $context)->getVersion());
        static::assertSame($first->getId(), $this->bindings->active('payment:transfer', $context)->getId());

        $head = $this->bindings->activate('payment:transfer', 2, 1, $context);
        static::assertSame(2, $head->getVersion());
        static::assertSame($second->getId(), $this->bindings->active('payment:transfer', $context)->getId());
        static::assertSame($first->getReleaseId(), $this->bindings->read('payment:transfer', 1, $context)->getReleaseId());
        $head = $this->bindings->activate('payment:transfer', 1, 2, $context);
        static::assertSame(3, $head->getVersion());
        static::assertSame($first->getId(), $this->bindings->active('payment:transfer', $context)->getId());
    }

    public function testStaleEditorCannotAppendARevision(): void
    {
        $context = Context::createDefaultContext();
        $release = $this->publish();
        $this->bindings->save('payment:transfer', '1.0.0', $release, 0, $context);
        $this->expectExceptionObject(ApprovalFlowException::bindingRevisionConflict('payment:transfer', 0, 1));

        $this->bindings->save('payment:transfer', '1.0.0', $release, 0, $context);
    }

    public function testStaleActivationCannotReplaceTheCurrentChoice(): void
    {
        $context = Context::createDefaultContext();
        $this->bindings->save('payment:transfer', '1.0.0', $this->publish(), 0, $context);
        $this->bindings->activate('payment:transfer', 1, 0, $context);
        $this->expectExceptionObject(ApprovalFlowException::bindingVersionConflict('payment:transfer', 0, 1));

        $this->bindings->activate('payment:transfer', 1, 0, $context);
    }

    public function testBusinessTypeMustMatchTheStartNode(): void
    {
        $context = Context::createDefaultContext();
        $specification = new ApprovalBusinessTypeSpecification('expense:claim', '1.0.0', 'expense', 'test:expense', $this->businessType->formSchema);
        $service = $this->service($specification);
        $this->expectExceptionObject(ApprovalFlowException::bindingReleaseMismatch('expense:claim', 'businessType'));

        $service->save('expense:claim', '1.0.0', $this->publish(), 0, $context);
    }

    public function testFormIdentityMustMatchTheSelectedRelease(): void
    {
        $context = Context::createDefaultContext();
        $specification = new ApprovalBusinessTypeSpecification('payment:transfer', '1.0.0', 'transfer', 'test:payment', '{"$id":"payment:other","type":"object"}');
        $this->expectExceptionObject(ApprovalFlowException::bindingReleaseMismatch('payment:transfer', 'formSchemaRef'));

        $this->service($specification)->save('payment:transfer', '1.0.0', $this->publish(), 0, $context);
    }

    public function testMissingExactBusinessVersionCannotCreateABinding(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::businessTypeNotFound('payment:transfer', '2.0.0'));

        $this->bindings->save('payment:transfer', '2.0.0', Uuid::randomHex(), 0, Context::createDefaultContext());
    }

    public function testActivationRejectsChangesToTheFrozenForm(): void
    {
        $context = Context::createDefaultContext();
        $this->bindings->save('payment:transfer', '1.0.0', $this->publish(), 0, $context);
        $changed = new ApprovalBusinessTypeSpecification('payment:transfer', '1.0.0', 'transfer', 'test:payment', '{"$id":"payment:transfer","type":"object"}');
        try {
            $this->service($changed)->activate('payment:transfer', 1, 0, $context);
            static::fail('Activation must verify the frozen business declaration.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::BINDING_FORM_CHANGED, $exception->getErrorCode());
        }
        static::assertNull($this->bindings->head('payment:transfer', $context)->getActiveRevision());
        static::assertSame(0, $this->bindings->head('payment:transfer', $context)->getVersion());
    }

    public function testSavingCannotReenterActivationThroughADalEvent(): void
    {
        $context = Context::createDefaultContext();
        $releaseId = $this->publish();
        $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $context);
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_binding.written', function (EntityWrittenEvent $event): void {
            $this->bindings->activate('payment:transfer', 1, 0, $event->getContext());
        });
        try {
            $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 1, $context);
            static::fail('An event must not begin another change of the same binding.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame('APPROVAL_FLOW__BINDING_WRITE_IN_PROGRESS', $exception->getErrorCode());
        }
        $head = $this->bindings->head('payment:transfer', $context);
        static::assertSame(1, $head->getLatestRevision());
        static::assertNull($head->getActiveRevision());
        static::assertSame(0, $head->getVersion());
        $this->bindings->activate('payment:transfer', 1, 0, $context);
        static::assertSame(1, $this->bindings->head('payment:transfer', $context)->getVersion());
    }

    public function testActivationRechecksDeploymentArtifacts(): void
    {
        $context = Context::createDefaultContext();
        $this->bindings->save('payment:transfer', '1.0.0', $this->publish(), 0, $context);
        $this->filesystem->dumpFile($this->directory . '/core.artifact', 'Changed deployment bytes.');
        try {
            $this->bindings->activate('payment:transfer', 1, 0, $context);
            static::fail('Activation must recheck release artifacts.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_ARTIFACT_MANIFEST, $exception->getErrorCode());
        }
        static::assertNull($this->bindings->head('payment:transfer', $context)->getActiveRevision());
    }

    public function testDalListenerFailureRollsBackTheBindingAndHeadTogether(): void
    {
        $context = Context::createDefaultContext();
        $release = $this->publish();
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_binding.written', static function (EntityWrittenEvent $event): void {
            throw new \RuntimeException('Binding listener failed.');
        });
        try {
            $this->bindings->save('payment:transfer', '1.0.0', $release, 0, $context);
            static::fail('A failed save must roll back the allocation row too.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Binding listener failed.', $exception->getMessage());
        }
        $connection = static::getContainer()->get(Connection::class);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_binding_head'));
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_binding'));
        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
    }

    public function testValidationListenerCannotSaveTheSameBusinessAgain(): void
    {
        $releaseId = $this->publish();
        $context = Context::createDefaultContext();
        $reenter = true;
        $observedContexts = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalBindingValidationEvent::class, function (ApprovalBindingValidationEvent $event) use (&$reenter, &$observedContexts, $releaseId, $context): void {
            $observedContexts[] = $event->getContext();
            if ($reenter) {
                $reenter = false;
                $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $context);
            }
        });
        try {
            $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $context);
            static::fail('Business validation must not reenter a binding write.');
        } catch (ApprovalFlowException $exception) {
            static::assertEquals(ApprovalFlowException::bindingWriteInProgress('payment:transfer'), $exception);
        }
        static::assertSame([$context], $observedContexts);
        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_binding_head'));
        static::assertSame(1, $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $context)->getRevision());
    }

    public function testValidationListenerCannotActivateAnotherRevisionDuringSave(): void
    {
        $releaseId = $this->publish();
        $context = Context::createDefaultContext();
        $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $context);
        $reenter = true;
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalBindingValidationEvent::class, function () use (&$reenter, $context): void {
            if ($reenter) {
                $reenter = false;
                $this->bindings->activate('payment:transfer', 1, 0, $context);
            }
        });
        try {
            $this->bindings->save('payment:transfer', '1.0.0', $releaseId, 1, $context);
            static::fail('Business validation must not change the active binding.');
        } catch (ApprovalFlowException $exception) {
            static::assertEquals(ApprovalFlowException::bindingWriteInProgress('payment:transfer'), $exception);
        }
        $head = $this->bindings->head('payment:transfer', $context);
        static::assertSame(1, $head->getLatestRevision());
        static::assertSame(0, $head->getVersion());
        static::assertNull($head->getActiveRevision());
    }

    private function service(ApprovalBusinessTypeSpecification $specification): ApprovalBindingService
    {
        $loader = new class($specification) implements ApprovalBusinessTypeLoader {
            public function __construct(private readonly ApprovalBusinessTypeSpecification $specification)
            {
            }

            public function load(): array
            {
                return [$this->specification];
            }
        };

        return new ApprovalBindingService(
            static::getContainer()->get(ApprovalBindingStore::class),
            new ApprovalBusinessTypeRegistry([$loader]),
            $this->runtime->get(ApprovalReleaseLoader::class),
            static::getContainer()->get('event_dispatcher'),
        );
    }

    private function publish(string $name = 'Transfer approval'): string
    {
        $context = Context::createDefaultContext();
        $draft = static::getContainer()->get(ApprovalDraftService::class)->create($name, GraphFixture::json(), $context);
        $compiled = $this->runtime->get(ApprovalReleaseCompiler::class)->compile($draft->graph);

        return $this->runtime->get(ApprovalReleaseService::class)->publish($draft->definitionId, 1, $compiled->hash, $context)->id;
    }
}
