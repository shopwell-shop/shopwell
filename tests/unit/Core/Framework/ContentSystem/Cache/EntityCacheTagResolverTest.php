<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityCacheTagResolver::class)]
class EntityCacheTagResolverTest extends TestCase
{
    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function knownEntityProvider(): \Generator
    {
        yield 'product' => ['product', 'product-abc123'];
        yield 'category' => ['category', 'category-route-abc123'];
        yield 'landing page' => ['landing_page', 'landing-page-route-abc123'];
        yield 'product stream' => ['product_stream', 'product-stream-abc123'];
    }

    #[DataProvider('knownEntityProvider')]
    #[TestDox('resolves $entityName to cache tag with correct prefix')]
    public function testResolvesKnownEntityTypesToCacheTags(string $entityName, string $expectedTag): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $resolver = new EntityCacheTagResolver();

        static::assertSame($expectedTag, $resolver->resolve($definition, 'abc123'));
    }

    #[DataProvider('unsupportedEntityProvider')]
    #[TestDox('returns null for unsupported entity type')]
    public function testReturnsNullForUnsupportedEntity(string $entityName): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $resolver = new EntityCacheTagResolver();

        static::assertNull($resolver->resolve($definition, 'abc123'));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function unsupportedEntityProvider(): \Generator
    {
        yield 'order' => ['order'];
        yield 'retired CMS page' => ['cms_page'];
    }
}
