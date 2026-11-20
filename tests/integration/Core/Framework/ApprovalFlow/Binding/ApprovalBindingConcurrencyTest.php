<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Binding;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingStore;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalBindingConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('operations')]
    public function testCompetingOperationsUseCurrentReadsDespiteAnOlderSnapshot(string $mode, string $conflict): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $store = $container->get(ApprovalBindingStore::class);
        $type = $mode === 'first-head' ? 'payment:transfer' : 'test:binding_' . Uuid::randomHex();
        $business = new ApprovalBusinessTypeSpecification($type, '1.0.0', 'transfer', 'test:payment', '{"$id":"payment:transfer","type":"object"}');
        $releaseId = InstanceFixture::publish($container);
        $definitionId = $connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
        $definitions = [$releaseId => $definitionId];
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/approval-binding-race-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($filesystem, $directory);
        $runtime = ApprovalRuntimeFixture::container($container, $manifest);
        $context = Context::createDefaultContext();
        $input = new InputStream();
        $writer = new Process([\PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-binding.php', $mode, $type, $releaseId, $manifest]);
        $writer->setTimeout(30);
        $writer->setInput($input);

        try {
            if ($mode === 'activate') {
                $store->save($business, $releaseId, 0, $context, static function (): void {});
            }
            $writer->start();
            $this->waitFor($writer, $mode === 'activate' ? 'SNAPSHOT:1' : 'SNAPSHOT:0');
            $connection->beginTransaction();
            if ($mode === 'first-head') {
                $store->save($business, $releaseId, 0, $context, static function (): void {});
                $releaseId = ApprovalRuntimeFixture::publish($container, $runtime, GraphFixture::json());
                $definitions[$releaseId] = $connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
            }
            $store->save($business, $releaseId, $mode === 'save' ? 0 : 1, $context, static function (): void {});
            if ($mode !== 'save') {
                $store->activate($type, 1, 0, $context, static function (): void {});
            }
            $input->write("compete\n");
            $this->waitFor($writer, 'BLOCKED');
            $connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getOutput() . $writer->getErrorOutput());
            static::assertStringContainsString('CONFLICT:' . $conflict, $writer->getOutput());
            $head = $store->head($type, $context);
            if ($mode !== 'save') {
                static::assertStringContainsString('ACTIVATED:2:2', $writer->getOutput());
                static::assertSame(1, $head->getActiveRevision());
                static::assertSame(1, $head->getVersion());
            } else {
                static::assertSame(1, $head->getLatestRevision());
                static::assertNull($head->getActiveRevision());
            }
        } finally {
            $input->close();
            $writer->stop();
            while ($connection->getTransactionNestingLevel() > 0) {
                $connection->rollBack();
            }
            $connection->delete('approval_flow_binding_head', ['business_type' => $type]);
            $connection->delete('approval_flow_binding', ['business_type' => $type]);
            foreach ($definitions as $id => $definitionId) {
                $connection->delete('approval_flow_release', ['id' => Uuid::fromHexToBytes($id)]);
                $connection->delete('approval_flow_draft', ['definition_id' => $definitionId]);
                $connection->delete('approval_flow_definition', ['id' => $definitionId]);
            }
            $filesystem->remove($directory);
        }
    }

    public static function operations(): iterable
    {
        yield 'first save' => ['save', ApprovalFlowException::BINDING_REVISION_CONFLICT];
        yield 'activation' => ['activate', ApprovalFlowException::BINDING_VERSION_CONFLICT];
        yield 'new head and release after snapshot' => ['first-head', ApprovalFlowException::BINDING_VERSION_CONFLICT];
    }

    private function waitFor(Process $process, string $message): void
    {
        $received = $process->waitUntil(static fn (): bool => str_contains($process->getOutput(), $message));
        static::assertTrue($received, $process->getOutput() . $process->getErrorOutput());
    }
}
