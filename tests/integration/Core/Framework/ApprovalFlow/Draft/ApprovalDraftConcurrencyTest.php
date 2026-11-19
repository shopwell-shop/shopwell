<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Draft;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalDraftConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCompetingWriterLocksAndChecksTheCurrentRevisionDespiteAnOlderReadSnapshot(): void
    {
        $service = static::getContainer()->get('test.approval_flow.draft_service');
        static::assertInstanceOf(ApprovalDraftService::class, $service);
        $connection = static::getContainer()->get(Connection::class);
        $context = Context::createDefaultContext();
        $draft = $service->create('Transfer approval', GraphFixture::json(), $context);
        $input = new InputStream();
        $writer = new Process([\PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-writer.php', $draft->definitionId]);
        $writer->setTimeout(30);
        $writer->setInput($input);

        try {
            $writer->start();
            $this->waitFor($writer, 'SNAPSHOT:1');

            $connection->beginTransaction();
            $first = $service->removeConnection($draft->definitionId, 1, 'review-end', confirmRemoval: true, context: $context);
            $input->write("compete\n");
            // The child must time out on this row before the first writer releases it.
            $this->waitFor($writer, 'BLOCKED');
            $connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getErrorOutput());
            static::assertStringContainsString('CONFLICT:2', $writer->getOutput());
            $reloaded = $service->read($draft->definitionId, $context);
            static::assertSame(2, $reloaded->revision);
            static::assertSame($first->mutation->graph->json, $reloaded->graph->json);
        } finally {
            $input->close();
            $writer->stop();
            while ($connection->getTransactionNestingLevel() > 0) {
                $connection->rollBack();
            }
            $connection->delete('approval_flow_draft', ['definition_id' => Uuid::fromHexToBytes($draft->definitionId)]);
            $connection->delete('approval_flow_definition', ['id' => Uuid::fromHexToBytes($draft->definitionId)]);
        }
    }

    private function waitFor(Process $process, string $message): void
    {
        $received = $process->waitUntil(static fn (): bool => str_contains($process->getOutput(), $message));
        static::assertTrue($received, $process->getOutput() . $process->getErrorOutput());
    }
}
