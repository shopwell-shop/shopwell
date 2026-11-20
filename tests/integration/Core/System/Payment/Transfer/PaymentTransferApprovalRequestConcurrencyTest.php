<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestService;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestStore;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalStore;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalRequestConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('operations')]
    public function testCompetingSubmissionUsesCurrentFactsAfterWaitingForTheTransfer(string $mode): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/transfer-request-race-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($filesystem, $directory);
        $runtime = ApprovalRuntimeFixture::container($container, $manifest);
        $bindings = $runtime->get(ApprovalBindingService::class);
        $releaseId = ApprovalRuntimeFixture::publish($container, $runtime, GraphFixture::json());
        $definitionId = $connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
        $system = Context::createDefaultContext();
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 0, $system);
        $bindings->activate('payment:transfer', 1, 0, $system);
        $transfer = PaymentTransferApprovalFixture::create($container);
        $actor = Uuid::randomHex();
        $source = new AdminApiSource($actor);
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $context = Context::createDefaultContext($source);
        $service = new PaymentTransferApprovalRequestService(
            $container->get(PaymentTransferApprovalRequestStore::class),
            $runtime->get(ApprovalRequestService::class),
            $container->get(PaymentTransferApprovalStore::class),
            $container->get(PaymentTransferPresenter::class),
        );
        $input = new InputStream();
        $writer = new Process([\PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-approval-request.php', $mode, $transfer->id, $actor, $manifest]);
        $writer->setTimeout(30);
        $writer->setInput($input);

        try {
            $writer->start();
            $this->waitFor($writer, 'SNAPSHOT');
            $connection->beginTransaction();
            $request = null;
            $binding = null;
            if ($mode === 'new-after-legacy') {
                $container->get(PaymentTransferApprovalStore::class)->lock($transfer->id);
                $container->get('payment_transfer_approval.repository')->create([[
                    'id' => $transfer->id,
                    'releaseId' => Uuid::randomHex(),
                    'instanceId' => Uuid::randomHex(),
                    'initiatorId' => $actor,
                    'snapshot' => ['amount' => 100],
                ]], $system);
            } elseif ($mode === 'facts') {
                $connection->fetchOne('SELECT id FROM payment_transfer WHERE id = :id FOR UPDATE', ['id' => Uuid::fromHexToBytes($transfer->id)]);
                $container->get('payment_transfer.repository')->update([['id' => $transfer->id, 'metadata' => ['note' => 'Updated reason']]], $system);
                $container->get('payment_transfer_recipient.repository')->update([['id' => $transfer->recipientId, 'holderName' => 'Updated holder']], $system);
                $container->get('payment_account.repository')->update([['id' => $transfer->accountId, 'name' => 'Updated account']], $system);
                $binding = $bindings->save('payment:transfer', '1.0.0', $releaseId, 1, $system);
                $bindings->activate('payment:transfer', 2, 1, $system);
            } else {
                $request = $service->submit($transfer->id, $context);
            }
            $input->write("compete\n");
            $this->waitFor($writer, 'BLOCKED');
            $connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getOutput() . $writer->getErrorOutput());
            if ($mode === 'new-after-legacy' || $mode === 'legacy-after-new') {
                static::assertStringContainsString('REJECTED:approval_already_started', $writer->getOutput());
            } elseif ($mode === 'facts') {
                static::assertNotNull($binding);
                static::assertStringContainsString('"bindingId":"' . $binding->getId() . '"', $writer->getOutput());
                static::assertStringContainsString('Updated account', $writer->getOutput());
                static::assertStringContainsString('Updated holder', $writer->getOutput());
                static::assertStringContainsString('Updated reason', $writer->getOutput());
            } else {
                static::assertNotNull($request);
                static::assertStringContainsString('"id":"' . $request->getId() . '"', $writer->getOutput());
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_request WHERE business_id = :id', ['id' => $transfer->id]));
            }
        } finally {
            $input->close();
            $writer->stop();
            while ($connection->getTransactionNestingLevel() > 0) {
                $connection->rollBack();
            }
            $connection->delete('payment_transfer_approval_request', ['id' => Uuid::fromHexToBytes($transfer->id)]);
            $connection->delete('payment_transfer_approval', ['id' => Uuid::fromHexToBytes($transfer->id)]);
            $connection->delete('approval_flow_request', ['business_type' => 'payment:transfer', 'business_id' => $transfer->id]);
            $connection->delete('approval_flow_instance', ['release_id' => Uuid::fromHexToBytes($releaseId)]);
            $connection->delete('approval_flow_binding_head', ['business_type' => 'payment:transfer']);
            $connection->delete('approval_flow_binding', ['business_type' => 'payment:transfer']);
            $connection->delete('approval_flow_release', ['id' => Uuid::fromHexToBytes($releaseId)]);
            $connection->delete('approval_flow_draft', ['definition_id' => $definitionId]);
            $connection->delete('approval_flow_definition', ['id' => $definitionId]);
            $connection->delete('payment_transfer', ['id' => Uuid::fromHexToBytes($transfer->id)]);
            $connection->delete('payment_transfer_recipient', ['id' => Uuid::fromHexToBytes($transfer->recipientId)]);
            $connection->delete('payment_app', ['id' => Uuid::fromHexToBytes($transfer->paymentAppId)]);
            $connection->delete('payment_account', ['id' => Uuid::fromHexToBytes($transfer->accountId)]);
            $filesystem->remove($directory);
        }
    }

    public static function operations(): iterable
    {
        yield 'retry returns committed request after older snapshot' => ['retry'];
        yield 'new request waits for legacy approval' => ['new-after-legacy'];
        yield 'legacy approval waits for new request' => ['legacy-after-new'];
        yield 'new submission reads current binding and business facts' => ['facts'];
    }

    private function waitFor(Process $process, string $message): void
    {
        $received = $process->waitUntil(static fn (): bool => str_contains($process->getOutput(), $message));
        static::assertTrue($received, $process->getOutput() . $process->getErrorOutput());
    }
}
