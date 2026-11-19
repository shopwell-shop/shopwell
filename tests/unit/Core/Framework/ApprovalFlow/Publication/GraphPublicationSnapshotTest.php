<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationSnapshot;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphPublicationSnapshot::class)]
class GraphPublicationSnapshotTest extends TestCase
{
    public function testHashesCoverBothTheGraphAndItsFrozenContract(): void
    {
        $snapshot = new GraphPublicationSnapshot(graphJson: '{"id":"first"}', contractJson: '{"schema":"first"}');
        $changedGraph = new GraphPublicationSnapshot(graphJson: '{"id":"second"}', contractJson: $snapshot->contractJson);
        $changedContract = new GraphPublicationSnapshot(graphJson: $snapshot->graphJson, contractJson: '{"schema":"second"}');

        static::assertSame(hash('sha256', $snapshot->graphJson), $snapshot->graphHash);
        static::assertSame(hash('sha256', json_encode([
            'graph' => $snapshot->graphJson,
            'contract' => $snapshot->contractJson,
        ], \JSON_THROW_ON_ERROR)), $snapshot->hash);
        static::assertNotSame($snapshot->graphHash, $changedGraph->graphHash);
        static::assertNotSame($snapshot->hash, $changedGraph->hash);
        static::assertSame($snapshot->graphHash, $changedContract->graphHash);
        static::assertNotSame($snapshot->hash, $changedContract->hash);
    }
}
