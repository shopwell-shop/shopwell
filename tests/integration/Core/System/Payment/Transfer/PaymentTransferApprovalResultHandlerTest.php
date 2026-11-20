<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultDispatcher;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultOutbox;
use Shopwell\Core\Framework\ApprovalFlow\ScheduledTask\DeliverApprovalResultsTask;
use Shopwell\Core\Framework\ApprovalFlow\ScheduledTask\DeliverApprovalResultsTaskHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestService;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestStore;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalResultHandler;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalStore;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalResultHandlerTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private string $reviewerId;

    private string $releaseId;

    private string $definitionId;

    private PaymentTransferEntity $transfer;

    private ApprovalRequestEntity $request;

    protected function setUp(): void
    {
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/transfer-result-' . Uuid::randomHex();
        $this->runtime = ApprovalRuntimeFixture::container($kernel, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $this->reviewerId = Uuid::randomHex();
        $locale = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');
        static::assertIsString($locale);
        $kernel->get('user.repository')->create([[
            'id' => $this->reviewerId, 'username' => $this->reviewerId, 'name' => 'Transfer reviewer',
            'email' => $this->reviewerId . '@example.test', 'password' => 'shopwell', 'active' => true,
            'localeId' => Uuid::fromBytesToHex($locale),
        ]], Context::createDefaultContext());
        $this->releaseId = ApprovalRuntimeFixture::publish($kernel, $this->runtime, str_replace('finance-user', $this->reviewerId, GraphFixture::json()));
        $definition = $this->connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->releaseId)]);
        static::assertIsString($definition);
        $this->definitionId = $definition;
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $this->releaseId, 0, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
        $this->transfer = PaymentTransferApprovalFixture::create($kernel);
        $source = new AdminApiSource($this->reviewerId);
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $service = new PaymentTransferApprovalRequestService(
            $kernel->get(PaymentTransferApprovalRequestStore::class),
            $this->runtime->get(ApprovalRequestService::class),
            $kernel->get(PaymentTransferApprovalStore::class),
            $kernel->get(PaymentTransferPresenter::class),
        );
        $this->request = $service->submit($this->transfer->id, Context::createDefaultContext($source));
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        $transferId = Uuid::fromHexToBytes($this->transfer->id);
        $instanceId = Uuid::fromHexToBytes($this->request->getInstanceId());
        $this->connection->delete('payment_transfer_approval_result', ['id' => $transferId]);
        $this->connection->executeStatement('DELETE d FROM approval_flow_outbox d INNER JOIN approval_flow_result r ON r.id = d.result_id WHERE r.instance_id = :id', ['id' => $instanceId]);
        $this->connection->delete('approval_flow_result', ['instance_id' => $instanceId]);
        $this->connection->delete('payment_transfer_approval_request', ['id' => $transferId]);
        $this->connection->delete('approval_flow_request', ['id' => Uuid::fromHexToBytes($this->request->getId())]);
        $this->connection->executeStatement('DELETE t FROM approval_flow_task t INNER JOIN approval_flow_execution e ON e.id = t.execution_id WHERE e.instance_id = :id', ['id' => $instanceId]);
        foreach (['approval_flow_execution', 'approval_flow_operation'] as $table) {
            $this->connection->delete($table, ['instance_id' => $instanceId]);
        }
        $this->connection->delete('approval_flow_instance', ['id' => $instanceId]);
        $this->connection->delete('approval_flow_binding_head', ['business_type' => 'payment:transfer']);
        $this->connection->delete('approval_flow_binding', ['business_type' => 'payment:transfer']);
        $this->connection->delete('approval_flow_release', ['id' => Uuid::fromHexToBytes($this->releaseId)]);
        $this->connection->delete('approval_flow_draft', ['definition_id' => $this->definitionId]);
        $this->connection->delete('approval_flow_definition', ['id' => $this->definitionId]);
        $this->connection->delete('payment_transfer', ['id' => $transferId]);
        $this->connection->delete('payment_transfer_recipient', ['id' => Uuid::fromHexToBytes($this->transfer->recipientId)]);
        $this->connection->delete('payment_app', ['id' => Uuid::fromHexToBytes($this->transfer->paymentAppId)]);
        $this->connection->delete('payment_account', ['id' => Uuid::fromHexToBytes($this->transfer->accountId)]);
        $this->connection->delete('user', ['id' => Uuid::fromHexToBytes($this->reviewerId)]);
        $this->filesystem->remove($this->directory);
    }

    #[DataProvider('outcomes')]
    public function testDeliveredResultRecordsTheDecisionWithoutStartingAPayout(ApprovalOutcome $outcome): void
    {
        $this->complete($outcome);

        static::getContainer()->get(DeliverApprovalResultsTaskHandler::class)->run();

        static::assertSame($outcome->value, $this->businessResult()['outcome']);
        static::assertSame('delivered', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT submit_attempts FROM payment_transfer WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->transfer->id)]));
        static::assertSame('created', $this->connection->fetchOne('SELECT s.technical_name FROM payment_transfer t INNER JOIN state_machine_state s ON s.id = t.status_id WHERE t.id = :id', ['id' => Uuid::fromHexToBytes($this->transfer->id)]));
    }

    public function testACommitBeforeAcknowledgementIsSafeToDeliverAgain(): void
    {
        $this->complete(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $delivery = static::getContainer()->get(ApprovalResultOutbox::class)->claim($context);
        static::assertNotNull($delivery);
        $handler = static::getContainer()->get(PaymentTransferApprovalResultHandler::class);

        $handler->handle($delivery->result, $this->request, $context);
        $first = $this->businessResult();
        $this->connection->update('approval_flow_outbox', ['lease_until' => '2000-01-01 00:00:00.000'], ['result_id' => Uuid::fromHexToBytes($delivery->result->id)]);
        static::getContainer()->get(ApprovalResultDispatcher::class)->dispatchPending();

        static::assertSame($first, $this->businessResult());
        static::assertSame('delivered', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox'));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT attempt FROM approval_flow_outbox'));
    }

    public function testConcurrentDeliveryReplaysTheCommittedBusinessResultFromAnOlderSnapshot(): void
    {
        $this->complete(ApprovalOutcome::Approved);
        $delivery = static::getContainer()->get(ApprovalResultOutbox::class)->claim(Context::createDefaultContext());
        static::assertNotNull($delivery);
        $input = new InputStream();
        $writer = new Process([\PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-approval-result.php', $delivery->result->id, $this->request->getId()]);
        $writer->setInput($input);
        $writer->setTimeout(30);
        try {
            $writer->start();
            static::assertTrue($writer->waitUntil(static fn (): bool => str_contains($writer->getOutput(), 'SNAPSHOT')), $writer->getErrorOutput());
            $this->connection->beginTransaction();
            static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($delivery->result, $this->request, Context::createDefaultContext());
            $input->write("compete\n");
            static::assertTrue($writer->waitUntil(static fn (): bool => str_contains($writer->getOutput(), 'BLOCKED')), $writer->getErrorOutput());
            $this->connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getErrorOutput());
            static::assertStringContainsString('REPLAYED', $writer->getOutput());
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_result'));
        } finally {
            $input->close();
            $writer->stop();
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }
    }

    public function testResultTaskIsDiscoveredByTheHostRegistry(): void
    {
        $this->connection->beginTransaction();
        try {
            static::getContainer()->get(TaskRegistry::class)->registerTasks();

            static::assertSame(DeliverApprovalResultsTask::class, $this->connection->fetchOne('SELECT scheduled_task_class FROM scheduled_task WHERE name = :name', ['name' => DeliverApprovalResultsTask::getTaskName()]));
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testConcurrentClaimRejectsChangedFactsFromAnOlderSnapshot(): void
    {
        $this->complete(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $delivery = static::getContainer()->get(ApprovalResultOutbox::class)->claim($context);
        static::assertNotNull($delivery);
        $input = new InputStream();
        $writer = new Process([\PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-approval-execution.php', $this->transfer->id]);
        $writer->setInput($input);
        $writer->setTimeout(30);
        try {
            $writer->start();
            static::assertTrue($writer->waitUntil(static fn (): bool => str_contains($writer->getOutput(), 'SNAPSHOT')), $writer->getErrorOutput());
            $this->connection->beginTransaction();
            static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($delivery->result, $this->request, $context);
            $identity = static::getContainer()->get(PaymentCredentialCipher::class)->encryptValue('Changed before the payout claim');
            static::getContainer()->get('payment_transfer_recipient.repository')->update([['id' => $this->transfer->recipientId, 'identityValue' => $identity]], $context);
            $input->write("compete\n");
            static::assertTrue($writer->waitUntil(static fn (): bool => str_contains($writer->getOutput(), 'BLOCKED')), $writer->getErrorOutput());
            $this->connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getErrorOutput());
            static::assertStringContainsString('REJECTED', $writer->getOutput());
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT submit_attempts FROM payment_transfer WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->transfer->id)]));
        } finally {
            $input->close();
            $writer->stop();
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }
    }

    public function testChangedTransferFactsCannotAcquireTheApprovedResult(): void
    {
        $this->complete(ApprovalOutcome::Approved);
        static::getContainer()->get('payment_transfer.repository')->update([['id' => $this->transfer->id, 'metadata' => ['note' => 'Changed after submission']]], Context::createDefaultContext());

        static::getContainer()->get(ApprovalResultDispatcher::class)->dispatchPending();

        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_result'));
        static::assertSame('pending', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox'));
        static::assertSame('approved', $this->connection->fetchOne('SELECT outcome FROM approval_flow_result'));
    }

    public function testFailedBusinessWriteLeavesTheResultRetryable(): void
    {
        $this->complete(ApprovalOutcome::Approved);
        EventHookDispatcher::fromContainer(static::getContainer())->on('payment_transfer_approval_result.written', static function (EntityWrittenEvent $event): void {
            throw new \RuntimeException('Business result write failed.');
        }, once: true);

        static::getContainer()->get(ApprovalResultDispatcher::class)->dispatchPending();

        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM payment_transfer_approval_result'));
        static::assertSame('pending', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox'));
        $this->connection->executeStatement('UPDATE approval_flow_outbox SET next_attempt_at = \'2000-01-01 00:00:00.000\'');
        static::getContainer()->get(ApprovalResultDispatcher::class)->dispatchPending();
        static::assertSame('approved', $this->businessResult()['outcome']);
        static::assertSame('delivered', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox'));
    }

    public function testTheRecordedBusinessDecisionCannotBeRewrittenThroughDal(): void
    {
        $this->complete(ApprovalOutcome::Approved);
        static::getContainer()->get(DeliverApprovalResultsTaskHandler::class)->run();
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('immutable_transfer'));

        static::getContainer()->get('payment_transfer_approval_result.repository')->update([['id' => $this->transfer->id, 'outcome' => 'rejected']], Context::createDefaultContext());
    }

    public static function outcomes(): iterable
    {
        yield 'approved' => [ApprovalOutcome::Approved];
        yield 'rejected' => [ApprovalOutcome::Rejected];
    }

    private function complete(ApprovalOutcome $outcome): void
    {
        $task = $this->connection->fetchAssociative('SELECT id, execution_id FROM approval_flow_task WHERE assignee_id = :id', ['id' => Uuid::fromHexToBytes($this->reviewerId)]);
        static::assertNotFalse($task);
        $source = new AdminApiSource($this->reviewerId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $instances = $this->runtime->get(ApprovalInstanceService::class);
        $instances->decide($this->request->getInstanceId(), Uuid::fromBytesToHex($task['id']), Uuid::randomHex(), 2, $outcome, '', Context::createDefaultContext($source));
        $instances->progress($this->request->getInstanceId(), Uuid::fromBytesToHex($task['execution_id']), Uuid::randomHex(), 3, Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function businessResult(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM payment_transfer_approval_result WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->transfer->id)]);
        static::assertNotFalse($row);
        static::assertSame(Uuid::fromHexToBytes($this->request->getId()), $row['request_id']);

        return $row;
    }
}
