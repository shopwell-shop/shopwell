<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Resolver mechanics against a synthetic table; the shipped `entity_group` table is covered by
 * {@see \Shopwell\Tests\Integration\Core\Framework\Telemetry\ShippedLabelMappingsTest}.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityGroupResolver::class)]
class EntityGroupResolverTest extends TestCase
{
    #[DataProvider('entityProvider')]
    public function testResolve(string $entityName, string $expected): void
    {
        $resolver = new EntityGroupResolver(new LabelMappingRegistry([
            EntityGroupResolver::MAPPING => [
                'product' => 'product',
                'product_*' => 'product',
                'main_category' => 'category',
                'custom_entity' => 'system',
            ],
        ], 'other'));

        static::assertSame($expected, $resolver->resolve($entityName));
    }

    public static function entityProvider(): \Generator
    {
        yield 'exact entry' => ['product', 'product'];
        yield 'prefix entry' => ['product_price', 'product'];
        yield 'exact entry without a matching prefix' => ['main_category', 'category'];
        yield 'exact entry does not act as prefix' => ['custom_entity_blog', 'other'];
        yield 'unlisted entity is other' => ['totally_unknown', 'other'];
    }
}
