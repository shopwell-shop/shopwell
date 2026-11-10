<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Aggregate\BlogContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogListingLoaderConfigSerializer;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogListingLoaderConfigSerializer::class)]
class BlogListingLoaderConfigSerializerTest extends TestCase
{
    public function testConfigurationRoundTripsWithoutChangingPropertyReference(): void
    {
        $serializer = new BlogListingLoaderConfigSerializer();
        $wire = ['property' => 'categoryId'];

        static::assertSame($wire, $serializer->encode($serializer->decode($wire)));
        static::assertSame($wire, $serializer->decode($wire)->jsonSerialize());
    }

    public function testEmptyPropertyReferenceIsRejected(): void
    {
        $this->expectException(BlogException::class);

        (new BlogListingLoaderConfigSerializer())->decode(['property' => '']);
    }

    public function testPublicLoaderConfigurationCannotEnableDraftPreview(): void
    {
        $this->expectException(BlogException::class);

        (new BlogListingLoaderConfigSerializer())->decode(['property' => 'categoryId', 'includeUnpublished' => true]);
    }
}
