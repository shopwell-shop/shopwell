<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\IndexerQueuer;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Migration\V6_6\Migration1710493619ScheduleMediaPathIndexer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1710493619ScheduleMediaPathIndexer::class)]
class Migration1710493619ScheduleMediaPathIndexerTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1710493619, (new Migration1710493619ScheduleMediaPathIndexer())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $queuer = static::getContainer()->get(IndexerQueuer::class);
        $queuer->finishIndexer(['media.path.post_update']);
        $queuedIndexers = $queuer->getIndexers();

        static::assertArrayNotHasKey('media.path.post_update', $queuedIndexers);

        $m = new Migration1710493619ScheduleMediaPathIndexer();
        $m->update($this->connection);
        $m->update($this->connection);

        $queuedIndexers = $queuer->getIndexers();
        static::assertArrayHasKey('media.path.post_update', $queuedIndexers);
    }
}
