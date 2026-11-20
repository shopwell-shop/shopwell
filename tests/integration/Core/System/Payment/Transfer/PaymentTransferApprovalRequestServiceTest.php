<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntime;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeRegistry;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalPolicy;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestService;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestStore;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalResultVerifier;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalService;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalStore;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalRequestServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private PaymentTransferApprovalRequestService $service;

    private Context $context;

    private ApprovalBindingService $bindings;

    private string $releaseId;

    private string $reviewerId;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/transfer-request-' . Uuid::randomHex();
        $runtime = ApprovalRuntimeFixture::container($container, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $bindings = $this->bindings = $runtime->get(ApprovalBindingService::class);
        $this->reviewerId = TestUser::createNewTestUser($container->get(Connection::class))->getUserId();
        $graph = str_replace('finance-user', $this->reviewerId, GraphFixture::json());
        $releaseId = $this->releaseId = ApprovalRuntimeFixture::publish($container, $runtime, $graph);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 0, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
        $this->service = new PaymentTransferApprovalRequestService(
            $container->get(PaymentTransferApprovalRequestStore::class),
            $runtime->get(ApprovalRequestService::class),
            $container->get(PaymentTransferApprovalStore::class),
            $container->get(PaymentTransferPresenter::class),
        );
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $this->context = Context::createDefaultContext($source);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testSubmitsBusinessGeneratedFormAndFreezesPrivateEvidenceSeparately(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $request = $this->service->submit($transfer->id, $this->context);
        $form = json_decode($request->getFormJson(), associative: true, flags: \JSON_THROW_ON_ERROR);
        $row = $container->get(Connection::class)->fetchAssociative('SELECT request_id, snapshot FROM payment_transfer_approval_request WHERE id = :id', ['id' => Uuid::fromHexToBytes($transfer->id)]);

        static::assertSame($transfer->id, $request->getBusinessId());
        static::assertSame(100, $form['amount']);
        static::assertSame('CNY', $form['currency']);
        static::assertSame('Transfer claim account', $form['paymentAccount']);
        static::assertStringNotContainsString('recipient', $form['recipient']);
        static::assertArrayNotHasKey('identityValue', $form);
        static::assertNotFalse($row);
        static::assertSame($request->getId(), Uuid::fromBytesToHex($row['request_id']));
        $snapshot = json_decode($row['snapshot'], associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($transfer->recipient?->identityValue, $snapshot['recipient']['identityValue']);
        static::assertSame(0, $container->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
    }

    public function testSubmissionStartsTheInstanceAndCreatesTheReviewersTask(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $request = $this->service->submit($transfer->id, $this->context);
        $connection = $container->get(Connection::class);

        static::assertSame('running', $connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($request->getInstanceId())]));
        static::assertSame(Uuid::fromHexToBytes($this->reviewerId), $connection->fetchOne('SELECT assignee_id FROM approval_flow_task WHERE status = :status', ['status' => 'pending']));
        static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
        static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation'));
        static::assertSame(0, $container->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
    }

    public function testUnavailableReviewerRollsBackTheSubmissionAndAllowsRetry(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $container->get('user.repository')->update([['id' => $this->reviewerId, 'active' => false]], Context::createDefaultContext());
        try {
            $this->service->submit($transfer->id, $this->context);
            static::fail('A failed start must roll back the business submission.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::ASSIGNEE_UNAVAILABLE, $exception->getErrorCode());
        }
        $connection = $container->get(Connection::class);
        foreach (['approval_flow_request', 'approval_flow_instance', 'approval_flow_execution', 'approval_flow_task', 'approval_flow_operation', 'payment_transfer_approval_request'] as $table) {
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $table), $table);
        }
        $container->get('user.repository')->update([['id' => $this->reviewerId, 'active' => true]], Context::createDefaultContext());
        $this->service->submit($transfer->id, $this->context);
        static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
    }

    public function testFailedTaskListenerRollsBackTheBusinessSubmission(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        EventHookDispatcher::fromContainer($container)->on('approval_flow_task.written', static function (): void {
            throw new \RuntimeException('Task listener failed.');
        }, once: true);
        try {
            $this->service->submit($transfer->id, $this->context);
            static::fail('A failed task write must roll back the business evidence too.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Task listener failed.', $exception->getMessage());
        }
        $connection = $container->get(Connection::class);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_request'));
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_request'));
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_instance'));
        $this->service->submit($transfer->id, $this->context);
        static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
    }

    public function testRepeatedSubmissionReturnsTheOriginalRequest(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $first = $this->service->submit($transfer->id, $this->context);
        $this->bindings->save('payment:transfer', '1.0.0', $this->releaseId, 1, Context::createDefaultContext());
        $this->bindings->activate('payment:transfer', 2, 1, Context::createDefaultContext());
        $container->get('payment_transfer_recipient.repository')->update([['id' => $transfer->recipientId, 'payoutEnabled' => false]], Context::createDefaultContext());
        $second = $this->service->submit($transfer->id, $this->context);

        static::assertSame($first->getBindingId(), $second->getBindingId());
        static::assertSame($first->getFormJson(), $second->getFormJson());
        static::assertSame($first->getId(), $second->getId());
        static::assertSame($first->getInstanceId(), $second->getInstanceId());
        static::assertSame(1, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_request'));
        static::assertSame(1, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_request'));
        static::assertSame(1, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
        static::assertSame(1, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_operation'));
    }

    public function testLegacySubmissionCannotBindATransferAlreadyInTheNewFlow(): void
    {
        $transfer = PaymentTransferApprovalFixture::create(static::getContainer());
        $this->service->submit($transfer->id, $this->context);
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('approval_already_started'));

        static::getContainer()->get(PaymentTransferApprovalStore::class)->lock($transfer->id);
    }

    public function testNewSubmissionCannotBindATransferAlreadyInTheLegacyFlow(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $container->get('payment_transfer_approval.repository')->create([[
            'id' => $transfer->id, 'releaseId' => Uuid::randomHex(), 'instanceId' => Uuid::randomHex(),
            'initiatorId' => Uuid::randomHex(), 'snapshot' => ['amount' => 100],
        ]], Context::createDefaultContext());
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('approval_already_started'));

        $this->service->submit($transfer->id, $this->context);
    }

    public function testAnExecutedTransferCannotAcquireApproval(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $container->get('payment_transfer.repository')->update([['id' => $transfer->id, 'submitAttempts' => 1]], Context::createDefaultContext());
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('transfer_already_started'));

        $this->service->submit($transfer->id, $this->context);
    }

    public function testAnotherInitiatorCannotReplayTheRequest(): void
    {
        $transfer = PaymentTransferApprovalFixture::create(static::getContainer());
        $request = $this->service->submit($transfer->id, $this->context);
        $this->expectExceptionObject(ApprovalFlowException::requestInitiatorRequired($request->getId()));

        $this->service->submit($transfer->id, Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }

    public function testFailedPrivateFreezeRollsBackRequestAndInstance(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        EventHookDispatcher::fromContainer($container)->on('payment_transfer_approval_request.written', static function (): void {
            throw new \RuntimeException('Private evidence listener failed.');
        }, once: true);
        try {
            $this->service->submit($transfer->id, $this->context);
            static::fail('A failed evidence freeze must roll back the submission.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Private evidence listener failed.', $exception->getMessage());
        }
        $connection = $container->get(Connection::class);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_request'));
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_instance'));
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_request'));
        // A failed attempt must release the in-memory reentry guard too.
        static::assertSame($transfer->id, $this->service->submit($transfer->id, $this->context)->getBusinessId());
    }

    public function testPermissionFailureDoesNotFreezePaymentEvidence(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_request:create']);
        try {
            $this->service->submit($transfer->id, Context::createDefaultContext($source));
            static::fail('The DAL must require instance creation permission.');
        } catch (MissingPrivilegeException $exception) {
            static::assertSame((new MissingPrivilegeException(['approval_flow_instance:create']))->getMessage(), $exception->getMessage());
        }
        static::assertSame(0, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_request'));
    }

    public function testDalListenerCannotReenterTheSameTransferSubmission(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        EventHookDispatcher::fromContainer($container)->on('approval_flow_request.written', function (EntityWrittenEvent $event) use ($transfer): void {
            $this->service->submit($transfer->id, $event->getContext());
        }, once: true);
        try {
            $this->service->submit($transfer->id, $this->context);
            static::fail('A row lock alone does not stop same-connection event reentry.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame(PaymentTransferApprovalException::blocked('submission_in_progress')->getMessage(), $exception->getMessage());
        }
        static::assertSame(0, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_request'));
    }

    public function testPrivateEvidenceCannotBeEditedEvenByInternalDalWrites(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $this->service->submit($transfer->id, $this->context);
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('immutable_transfer'));

        $container->get('payment_transfer_approval_request.repository')->update([['id' => $transfer->id, 'snapshot' => ['amount' => 1]]], Context::createDefaultContext());
    }

    public function testNewRequestListenerCannotStartTheLegacyApprovalBeforePrivateFreeze(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $runtime = static::createMock(WorkflowRuntime::class);
        $runtime->method('name')->willReturn('test:approval');
        $runtime->method('transactional')->willReturnCallback(static fn (\Closure $operation): mixed => $container->get(Connection::class)->transactional(static fn (): mixed => $operation()));
        $runtime->expects($this->never())->method('start');
        $legacy = $this->legacyService($transfer, $runtime);
        $draft = $this->draft($transfer);
        EventHookDispatcher::fromContainer($container)->on('approval_flow_request.written', function (EntityWrittenEvent $event) use ($legacy, $draft): void {
            $legacy->book($draft, $event->getContext());
        }, once: true);
        try {
            $this->service->submit($transfer->id, $this->context);
            static::fail('A legacy start from the new request listener must be blocked.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame('submission_in_progress', $exception->getParameters()['reason']);
        }
        static::assertSame(0, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_request'));
        static::assertSame(0, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval WHERE id = :id', ['id' => Uuid::fromHexToBytes($transfer->id)]));
    }

    public function testLegacyRuntimeCannotSubmitANewRequestBeforeItsBindingIsSaved(): void
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $runtime = static::createMock(WorkflowRuntime::class);
        $runtime->method('name')->willReturn('test:approval');
        $runtime->method('transactional')->willReturnCallback(static fn (\Closure $operation): mixed => $container->get(Connection::class)->transactional(static fn (): mixed => $operation()));
        $runtime->expects($this->once())->method('start')->willReturnCallback(function () use ($transfer): string {
            return $this->service->submit($transfer->id, $this->context)->getInstanceId();
        });
        $legacy = $this->legacyService($transfer, $runtime);
        try {
            $legacy->book($this->draft($transfer), $this->context);
            static::fail('The old runtime callback must not enter the new approval.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame('submission_in_progress', $exception->getParameters()['reason']);
        }
        static::assertSame(0, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM approval_flow_request'));
        static::assertSame(0, (int) $container->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval WHERE id = :id', ['id' => Uuid::fromHexToBytes($transfer->id)]));
    }

    private function draft(PaymentTransferEntity $transfer): PaymentTransferDraft
    {
        return new PaymentTransferDraft(
            paymentAppId: $transfer->paymentAppId,
            provider: $transfer->provider,
            channel: $transfer->channel,
            recipientId: $transfer->recipientId,
            amount: $transfer->amount,
            currency: $transfer->currency,
            outNo: $transfer->outNo,
            accountId: $transfer->accountId,
        );
    }

    private function legacyService(PaymentTransferEntity $transfer, WorkflowRuntime $runtime): PaymentTransferApprovalService
    {
        $container = static::getContainer();
        $system = Context::createDefaultContext();
        $container->get('payment_app.repository')->update([[
            'id' => $transfer->paymentAppId,
            'accounts' => [['accountId' => $transfer->accountId, 'enabled' => true, 'position' => 1]],
            'channels' => [['channelTechnicalName' => $transfer->channel, 'enabled' => true]],
        ]], $system);
        $document = json_decode((string) file_get_contents(\dirname(__DIR__, 5) . '/unit/Core/Framework/Workflow/Publication/Approval/_fixtures/default-approval-request.json'), associative: true, flags: \JSON_THROW_ON_ERROR);
        $document['nodes'][0]['config']['formFields'] = [
            ['key' => 'amount', 'type' => 'number', 'required' => true],
            ['key' => 'currency', 'type' => 'text', 'required' => true],
            ['key' => 'recipient', 'type' => 'text', 'required' => true],
            ['key' => 'paymentAccount', 'type' => 'text', 'required' => true],
            ['key' => 'reason', 'type' => 'text', 'required' => false],
        ];
        $releaseId = Uuid::randomHex();
        $container->get('workflow_release.repository')->create([[
            'id' => $releaseId, 'draftId' => Uuid::randomHex(), 'revision' => 1,
            'name' => 'Legacy transfer approval', 'publisher' => 'test:approval', 'publishedBy' => Uuid::randomHex(),
            'document' => json_encode($document, \JSON_THROW_ON_ERROR),
        ]], $system);
        $container->get(SystemConfigService::class)->set(PaymentTransferApprovalService::CONFIG_KEY, $releaseId);

        return new PaymentTransferApprovalService(
            $container->get(PaymentTransferApprovalStore::class),
            $container->get(PaymentTransferApprovalPolicy::class),
            $container->get(WorkflowReleaseStore::class),
            new WorkflowRuntimeRegistry([$runtime]),
            $container->get(SystemConfigService::class),
            $container->get(PaymentTransferBooking::class),
            $container->get(PaymentTransferService::class),
            $container->get(PaymentTransferPresenter::class),
            $container->get('event_dispatcher'),
            $container->get(PaymentTransferApprovalResultVerifier::class),
        );
    }
}
