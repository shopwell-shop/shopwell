<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Aggregate\BlogContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogDetailDataLoader;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogDetailLoaderConfig;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogSpecificationSource;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Service\BlogDetailReader;
use Shopwell\Core\Content\Blog\Service\BlogDetailStruct;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogDetailDataLoader::class)]
class BlogDetailDataLoaderTest extends TestCase
{
    public function testUnresolvedIdDoesNotReachTheReader(): void
    {
        $reader = $this->createMock(BlogDetailReader::class);
        $reader->expects($this->never())->method('read');
        $loader = new BlogDetailDataLoader($reader);
        $result = $loader->load(new LoaderInputs(['property' => '{{blogId}}']), $this->requirement(), Generator::generateSalesChannelContext(), new Request());

        static::assertFalse($result->hasData());
    }

    public function testUppercaseIdLoadsRealDetailWithRequestAndChannel(): void
    {
        $id = Uuid::randomHex();
        $request = new Request(['comments' => 2]);
        $context = Generator::generateSalesChannelContext();
        $detail = new BlogDetailStruct();
        $reader = $this->createMock(BlogDetailReader::class);
        $reader->expects($this->once())->method('read')->with($id, $request, $context, false)->willReturn($detail);
        $result = (new BlogDetailDataLoader($reader))->load(new LoaderInputs(['property' => strtoupper($id)]), $this->requirement(), $context, $request);

        static::assertSame($detail, $result->data);
        static::assertFalse($result->isCacheAware());
    }

    public function testMissingEntryDegradesToNoData(): void
    {
        $reader = static::createStub(BlogDetailReader::class);
        $reader->method('read')->willThrowException(BlogException::notFound('missing'));
        $result = (new BlogDetailDataLoader($reader))->load(new LoaderInputs(['property' => Uuid::randomHex()]), $this->requirement(), Generator::generateSalesChannelContext(), new Request());

        static::assertFalse($result->hasData());
    }

    public function testInfrastructureFaultPropagates(): void
    {
        $reader = static::createStub(BlogDetailReader::class);
        $fault = new \RuntimeException('Database unavailable');
        $reader->method('read')->willThrowException($fault);
        $this->expectExceptionObject($fault);

        (new BlogDetailDataLoader($reader))->load(new LoaderInputs(['property' => Uuid::randomHex()]), $this->requirement(), Generator::generateSalesChannelContext(), new Request());
    }

    public function testOnlyServerPreviewAttributeCanIncludeDrafts(): void
    {
        $id = Uuid::randomHex();
        $request = new Request(['includeUnpublished' => true, BlogSpecificationSource::PREVIEW_ATTRIBUTE => true]);
        $context = Generator::generateSalesChannelContext();
        $reader = $this->createMock(BlogDetailReader::class);
        $reader->expects($this->exactly(2))->method('read')->willReturnCallback(
            static function (string $blogId, Request $received, mixed $channel, bool $includeUnpublished) use ($id, $request): BlogDetailStruct {
                static::assertSame($id, $blogId);
                static::assertSame($request, $received);
                static::assertSame($request->attributes->get(BlogSpecificationSource::PREVIEW_ATTRIBUTE) === true, $includeUnpublished);

                return new BlogDetailStruct();
            }
        );
        $loader = new BlogDetailDataLoader($reader);
        $loader->load(new LoaderInputs(['property' => $id]), $this->requirement(), $context, $request);
        $request->attributes->set(BlogSpecificationSource::PREVIEW_ATTRIBUTE, true);
        $loader->load(new LoaderInputs(['property' => $id]), $this->requirement(), $context, $request);
    }

    private function requirement(): DataRequirement
    {
        return new DataRequirement('blogDetail', BlogDetailDataLoader::SOURCE, new BlogDetailLoaderConfig('blogId'));
    }
}
