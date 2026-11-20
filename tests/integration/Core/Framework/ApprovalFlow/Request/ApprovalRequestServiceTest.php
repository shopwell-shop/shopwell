<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Request;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\Api\Sync\SyncOperation;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
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
class ApprovalRequestServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private ApprovalBindingService $bindings;

    private ApprovalRequestService $requests;

    private string $bindingId;

    private string $releaseId;

    private Context $context;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-request-' . Uuid::randomHex();
        $this->runtime = ApprovalRuntimeFixture::container($container, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $this->bindings = $this->runtime->get(ApprovalBindingService::class);
        $this->requests = $this->runtime->get(ApprovalRequestService::class);
        $this->releaseId = ApprovalRuntimeFixture::publish($container, $this->runtime, GraphFixture::json());
        $system = Context::createDefaultContext();
        $this->bindingId = $this->bindings->save('payment:transfer', '1.0.0', $this->releaseId, 0, $system)->getId();
        $this->bindings->activate('payment:transfer', 1, 0, $system);
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $this->context = Context::createDefaultContext($source);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testCreatesAnImmutableRequestAndItsDraftInstanceTogether(): void
    {
        $businessId = Uuid::randomHex();
        $form = $this->form();
        $request = $this->requests->create('payment:transfer', $businessId, 1, hash('sha256', 'business revision'), $form, $this->context);
        $connection = static::getContainer()->get(Connection::class);
        $instance = $connection->fetchAssociative('SELECT status, aggregate_version, release_id FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($request->getInstanceId())]);

        static::assertSame($businessId, $request->getBusinessId());
        static::assertSame($this->bindingId, $request->getBindingId());
        static::assertSame($form, $request->getFormJson());
        $source = $this->context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertSame($source->getUserId(), $request->getInitiatorId());
        static::assertNotFalse($instance);
        static::assertSame('draft', $instance['status']);
        static::assertSame(1, (int) $instance['aggregate_version']);
        static::assertSame($this->releaseId, Uuid::fromBytesToHex($instance['release_id']));
        static::assertSame($request->getId(), $this->requests->find('payment:transfer', $businessId, 1, $this->context)?->getId());
        static::assertSame(Context::USER_SCOPE, $this->context->getScope());
    }

    public function testDalPermissionFailureRollsBackBothFacts(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_request:create']);
        try {
            $this->requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), $this->form(), Context::createDefaultContext($source));
            static::fail('The DAL must authorize instance creation too.');
        } catch (MissingPrivilegeException $exception) {
            static::assertSame((new MissingPrivilegeException(['approval_flow_instance:create']))->getMessage(), $exception->getMessage());
        }
        $connection = static::getContainer()->get(Connection::class);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_request'));
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_instance'));
    }

    public function testRejectsInvalidFormBeforeWritingAnInstance(): void
    {
        $form = str_replace('"amount":100', '"amount":1.5', $this->form());
        try {
            $this->requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), $form, $this->context);
            static::fail('The frozen form schema requires an integer amount.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_REQUEST_FORM, $exception->getErrorCode());
            static::assertSame('/amount', $exception->getParameters()['path']);
        }
        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_instance'));
    }

    public function testActivationDoesNotRebindAnExistingRequest(): void
    {
        $businessId = Uuid::randomHex();
        $first = $this->requests->create('payment:transfer', $businessId, 1, hash('sha256', 'revision'), $this->form(), $this->context);
        $secondRelease = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, GraphFixture::json());
        $this->bindings->save('payment:transfer', '1.0.0', $secondRelease, 1, Context::createDefaultContext());
        $this->bindings->activate('payment:transfer', 2, 1, Context::createDefaultContext());
        $second = $this->requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), $this->form(), $this->context);

        static::assertSame($this->bindingId, $this->requests->find('payment:transfer', $businessId, 1, $this->context)?->getBindingId());
        static::assertNotSame($first->getBindingId(), $second->getBindingId());
    }

    public function testDuplicateBusinessIdentityDoesNotLeaveAnotherDraftInstance(): void
    {
        $businessId = Uuid::randomHex();
        $first = $this->requests->create('payment:transfer', $businessId, 1, hash('sha256', 'revision'), $this->form(), $this->context);
        try {
            $this->requests->create('payment:transfer', $businessId, 1, hash('sha256', 'revision'), $this->form(), $this->context);
            static::fail('Each business submission round must have only one request.');
        } catch (UniqueConstraintViolationException) {
            static::assertSame($first->getId(), $this->requests->find('payment:transfer', $businessId, 1, $this->context)?->getId());
        }
        static::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_instance'));
    }

    public function testOrdinaryDalCannotCreateRequestFacts(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());
        static::getContainer()->get('approval_flow_request.repository')->create([[
            'id' => Uuid::randomHex(), 'businessType' => 'payment:transfer', 'businessId' => Uuid::randomHex(),
            'submissionRound' => 1, 'businessRevision' => hash('sha256', 'revision'), 'bindingId' => $this->bindingId,
            'initiatorId' => Uuid::randomHex(), 'formJson' => $this->form(),
            'instance' => ['id' => Uuid::randomHex(), 'releaseId' => $this->releaseId, 'status' => 'draft', 'aggregateVersion' => 1],
        ]], Context::createDefaultContext());
    }

    public function testSystemSourceCannotInventAnInitiator(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::requestActorRequired());
        $this->requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), $this->form(), Context::createDefaultContext());
    }

    public function testIntegrationSourceCannotInventAnInitiator(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::requestActorRequired());
        $source = new AdminApiSource(userId: null, integrationId: Uuid::randomHex());
        $this->requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), $this->form(), Context::createDefaultContext($source));
    }

    public function testAnotherUserCannotReadTheSubmissionAsARetry(): void
    {
        $businessId = Uuid::randomHex();
        $request = $this->requests->create('payment:transfer', $businessId, 1, hash('sha256', 'revision'), $this->form(), $this->context);
        $this->expectExceptionObject(ApprovalFlowException::requestInitiatorRequired($request->getId()));

        $this->requests->find('payment:transfer', $businessId, 1, Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }

    public function testSyncCannotRewriteFrozenFormFacts(): void
    {
        $request = $this->requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), $this->form(), $this->context);
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        static::getContainer()->get(EntityWriter::class)->sync([
            new SyncOperation('request', 'approval_flow_request', SyncOperation::ACTION_UPSERT, [
                ['id' => $request->getId(), 'formJson' => '{}'],
            ]),
        ], WriteContext::createFromContext(Context::createDefaultContext()));
    }

    public function testAWriteListenerCannotUseTheRequestContextForAnotherBatch(): void
    {
        $container = static::getContainer();
        EventHookDispatcher::fromContainer($container)->on(EntityWriteEvent::class, function (EntityWriteEvent $event) use ($container): void {
            if ($event->getContext() !== $this->context) {
                return;
            }
            $container->get('approval_flow_instance.repository')->create([[
                'id' => Uuid::randomHex(), 'releaseId' => $this->releaseId, 'status' => 'draft', 'aggregateVersion' => 1,
            ]], $event->getContext());
        }, once: true);
        try {
            $this->requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), $this->form(), $this->context);
            static::fail('The request permit must be consumed before listeners run.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::UNCONTROLLED_INSTANCE_WRITE, $exception->getErrorCode());
        }
        static::assertSame(Context::USER_SCOPE, $this->context->getScope());
        static::assertSame(0, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_instance'));
    }

    private function form(): string
    {
        return '{"amount":100,"currency":"CNY","recipient":"Li ••••1234","paymentAccount":"Merchant","reason":null}';
    }
}
