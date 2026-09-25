<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Elasticsearch\Migration\Traits;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Elasticsearch\Migration\Traits\ElasticsearchTriggerTrait;

/**
 * @internal
 */
#[Package('inventory')]
class ElasticsearchTriggerTraitTest extends TestCase
{
    use ElasticsearchTriggerTrait;
    use IntegrationTestBehaviour;

    public function testTrigger(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $connection = self::getContainer()->get(Connection::class);

        $this->triggerElasticsearchIndexing($connection);

        static::assertSame('["*"]', $this->fetchConfig($connection));
    }

    public function fetchConfig(Connection $connection): string
    {
        return $connection->fetchOne('SELECT `value` FROM app_config WHERE `key` = "elasticsearch.indexing.entities"');
    }
}
