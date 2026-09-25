<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\StorefrontPluginConfiguration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FileCollection::class)]
class FileCollectionTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $collection = FileCollection::createFromArray([
            'foo',
            'bar',
        ]);

        static::assertCount(2, $collection);
        static::assertSame(['foo', 'bar'], $collection->getFilepaths());
        static::assertSame([], $collection->getResolveMappings());
    }

    public function testResolveMappingGetsMerged(): void
    {
        $collection = new FileCollection([
            new File('foo', ['a' => 'b']),
            new File('bar', ['b' => 'c']),
        ]);

        static::assertSame(['a' => 'b', 'b' => 'c'], $collection->getResolveMappings());
    }

    public function testGetPublicPaths(): void
    {
        $collection = new FileCollection([
            new File('foo.js', [], null),
            new File('bar.js', [], 'bar'),
            new File('foo/bar.js', [], 'foo'),
        ]);

        static::assertSame(['baz/foo/bar.js'], $collection->getPublicPaths('baz'));
    }
}
