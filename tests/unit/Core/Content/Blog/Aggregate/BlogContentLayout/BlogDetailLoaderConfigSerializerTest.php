<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Aggregate\BlogContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogDetailLoaderConfigSerializer;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogDetailLoaderConfigSerializer::class)]
class BlogDetailLoaderConfigSerializerTest extends TestCase
{
    public function testConfigurationRoundTripsWithoutChangingPropertyReference(): void
    {
        $serializer = new BlogDetailLoaderConfigSerializer();
        $wire = ['property' => 'blogId'];

        static::assertSame($wire, $serializer->encode($serializer->decode($wire)));
        static::assertSame($wire, $serializer->decode($wire)->jsonSerialize());
    }

    public function testEmptyPropertyReferenceIsRejected(): void
    {
        $this->expectException(BlogException::class);

        (new BlogDetailLoaderConfigSerializer())->decode(['property' => '']);
    }

    public function testPublicLoaderConfigurationCannotEnableDraftPreview(): void
    {
        $this->expectException(BlogException::class);

        (new BlogDetailLoaderConfigSerializer())->decode(['property' => 'blogId', 'includeUnpublished' => true]);
    }
}
