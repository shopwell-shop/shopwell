<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Aggregate\BlogContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogListingDataLoader;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogListingLoaderConfig;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
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
#[CoversClass(BlogListingDataLoader::class)]
class BlogListingDataLoaderTest extends TestCase
{
    public function testCategoryListingUsesTypedCategoryAndRequestPagination(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $listing = new BlogListingStruct();
        $reader = $this->createMock(BlogListingReader::class);
        $reader->expects($this->once())->method('listing')->with('classic', $id, 3, $context)->willReturn($listing);
        $result = (new BlogListingDataLoader($reader))->load(new LoaderInputs(['property' => strtoupper($id)]), $this->requirement(), $context, new Request(['p' => 3, 'category' => Uuid::randomHex()]));

        static::assertSame($listing, $result->data);
        static::assertFalse($result->isCacheAware());
    }

    public function testHomeListingUsesNoCategory(): void
    {
        $context = Generator::generateSalesChannelContext();
        $reader = $this->createMock(BlogListingReader::class);
        $reader->expects($this->once())->method('listing')->with('classic', null, 1, $context)->willReturn(new BlogListingStruct());

        (new BlogListingDataLoader($reader))->load(new LoaderInputs(['property' => null]), $this->requirement(), $context, new Request(['p' => -1]));
    }

    public function testInvalidCategoryDoesNotReachTheReader(): void
    {
        $reader = $this->createMock(BlogListingReader::class);
        $reader->expects($this->never())->method('listing');
        $result = (new BlogListingDataLoader($reader))->load(new LoaderInputs(['property' => '{{categoryId}}']), $this->requirement(), Generator::generateSalesChannelContext(), new Request());

        static::assertFalse($result->hasData());
    }

    public function testInfrastructureFaultPropagates(): void
    {
        $reader = static::createStub(BlogListingReader::class);
        $fault = new \RuntimeException('Database unavailable');
        $reader->method('listing')->willThrowException($fault);
        $this->expectExceptionObject($fault);

        (new BlogListingDataLoader($reader))->load(new LoaderInputs(['property' => null]), $this->requirement(), Generator::generateSalesChannelContext(), new Request());
    }

    private function requirement(): DataRequirement
    {
        return new DataRequirement('listing', BlogListingDataLoader::SOURCE, new BlogListingLoaderConfig('categoryId'));
    }
}
