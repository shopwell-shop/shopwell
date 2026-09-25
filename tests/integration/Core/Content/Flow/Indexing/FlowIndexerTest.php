<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Flow\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\Indexing\FlowIndexerSubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;

/**
 * @internal
 */
#[Package('after-sales')]
class FlowIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    public function testIndexingHappensAfterPluginLifecycle(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('UPDATE `flow` SET `payload` = null, `invalid` = 0');

        $indexer = static::getContainer()->get(FlowIndexerSubscriber::class);
        $indexer->refreshPlugin();

        $this->runWorker();

        static::assertGreaterThan(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM flow WHERE payload IS NOT NULL'));
    }
}
