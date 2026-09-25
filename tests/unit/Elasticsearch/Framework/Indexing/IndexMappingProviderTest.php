<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopwell\Elasticsearch\Framework\Indexing\IndexMappingProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IndexMappingProvider::class)]
class IndexMappingProviderTest extends TestCase
{
    public function testBuild(): void
    {
        $mapping = [
            'foo' => 'bar',
        ];

        $definition = static::createStub(AbstractElasticsearchDefinition::class);
        $definition->method('getMapping')->willReturn([
            'bar' => 'foo',
        ]);

        $provider = new IndexMappingProvider($mapping);

        static::assertEquals(
            [
                'foo' => 'bar',
                'bar' => 'foo',
            ],
            $provider->build(
                $definition,
                Context::createDefaultContext()
            )
        );
    }
}
