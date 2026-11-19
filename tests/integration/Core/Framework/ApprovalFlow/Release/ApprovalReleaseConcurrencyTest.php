<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Release;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalReleaseConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCompetingPublisherReusesTheCommittedReleaseDespiteAnOlderSnapshot(): void
    {
        $container = static::getContainer();
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/approval-publishers-' . bin2hex(random_bytes(8));
        $filesystem->mkdir($directory);
        $path = PublicationFixture::writeManifest($filesystem, $directory);
        $compiler = PublicationFixture::releaseCompiler(
            $container->get(NodePlanCompiler::class),
            $container->get(NodeCompilerRegistry::class),
            $container->get(NodeExecutorRegistry::class),
            $filesystem,
            $path,
        );
        $publisher = new ApprovalReleaseService($container->get(ApprovalReleaseStore::class), $compiler);
        $connection = $container->get(Connection::class);
        $context = Context::createDefaultContext();
        $draft = $container->get(ApprovalDraftService::class)->create('Competing publication', GraphFixture::json(), $context);
        $content = $compiler->compile($draft->graph);
        $input = new InputStream();
        $writer = new Process([\PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-publisher.php', $draft->definitionId, $content->hash, $path]);
        $writer->setTimeout(30);
        $writer->setInput($input);

        try {
            $writer->start();
            $this->waitFor($writer, 'SNAPSHOT:0');
            $connection->beginTransaction();
            $first = $publisher->publish($draft->definitionId, 1, $content->hash, $context);
            $input->write("compete\n");
            $this->waitFor($writer, 'BLOCKED');
            $connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getErrorOutput());
            static::assertStringContainsString('REUSED:' . $first->id, $writer->getOutput());
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_release WHERE definition_id = :id', ['id' => Uuid::fromHexToBytes($draft->definitionId)]));
        } finally {
            $input->close();
            $writer->stop();
            while ($connection->getTransactionNestingLevel() > 0) {
                $connection->rollBack();
            }
            $connection->delete('approval_flow_release', ['definition_id' => Uuid::fromHexToBytes($draft->definitionId)]);
            $connection->delete('approval_flow_draft', ['definition_id' => Uuid::fromHexToBytes($draft->definitionId)]);
            $connection->delete('approval_flow_definition', ['id' => Uuid::fromHexToBytes($draft->definitionId)]);
            $filesystem->remove($directory);
        }
    }

    private function waitFor(Process $process, string $message): void
    {
        $received = $process->waitUntil(static fn (): bool => str_contains($process->getOutput(), $message));
        static::assertTrue($received, $process->getOutput() . $process->getErrorOutput());
    }
}
