<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationSource;
use Shopwell\Elasticsearch\DependencyInjection\ElasticsearchMigrationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchMigrationCompilerPass::class)]
class ElasticsearchMigrationCompilerPassTest extends TestCase
{
    public function testCompilerPass(): void
    {
        $container = new ContainerBuilder();
        $container->register(MigrationSource::class . '.core.V6_5', MigrationSource::class)->setPublic(true);

        $container->addCompilerPass(new ElasticsearchMigrationCompilerPass());
        $container->compile();

        $calls = $container->getDefinition(MigrationSource::class . '.core.V6_5')->getMethodCalls();
        static::assertCount(1, $calls);

        static::assertSame('addDirectory', $calls[0][0]);
        static::assertStringContainsString('Migration/V6_5', $calls[0][1][0]);
        static::assertSame('Shopwell\Elasticsearch\Migration\V6_5', $calls[0][1][1]);
    }
}
