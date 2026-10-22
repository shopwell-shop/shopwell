<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\BlogListingCmsElementResolver;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The element is the only place where a `blog-listing` slot decides which entries to show, and it
 * has to keep two promises: it never runs its own query, and an editor who drops it on a page without
 * opening the settings gets the classic layout instead of an empty container class.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogListingCmsElementResolver::class)]
class BlogListingCmsElementResolverTest extends TestCase
{
    public function testTheElementHasItsOwnTypeAndCollectsNoCriteriaOfItsOwn(): void
    {
        $resolver = new BlogListingCmsElementResolver(static::createStub(BlogListingReader::class));

        static::assertSame('blog-listing', $resolver->getType());
        static::assertNull($resolver->collect(
            new CmsSlotEntity(),
            new ResolverContext(Generator::generateSalesChannelContext(), new Request()),
        ));
    }

    public function testAnUnconfiguredElementFallsBackToTheClassicLayoutAndTheFirstPage(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request();

        $listing = new BlogListingStruct();
        $reader = $this->createMock(BlogListingReader::class);
        $reader->expects($this->once())
            ->method('listing')
            ->with(BlogListingStruct::LAYOUT_CLASSIC, null, 1, $salesChannelContext)
            ->willReturn($listing);

        $slot = new CmsSlotEntity();

        (new BlogListingCmsElementResolver($reader))->enrich(
            $slot,
            new ResolverContext($salesChannelContext, $request),
            static::createStub(ElementDataCollection::class),
        );

        static::assertSame($listing, $slot->getData());
    }

    public function testACategoryPageRendersThatCategoryWithTheConfiguredLayoutAndPage(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request(['p' => '3']);

        $category = new BlogCategoryEntity();
        $category->id = 'category-id';
        $category->setUniqueIdentifier('category-id');

        $listing = new BlogListingStruct();
        $reader = $this->createMock(BlogListingReader::class);
        $reader->expects($this->once())
            ->method('listing')
            ->with(BlogListingStruct::LAYOUT_LIST, 'category-id', 3, $salesChannelContext)
            ->willReturn($listing);

        $slot = new CmsSlotEntity();
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('layout', FieldConfig::SOURCE_STATIC, BlogListingStruct::LAYOUT_LIST),
        ]));

        (new BlogListingCmsElementResolver($reader))->enrich(
            $slot,
            new EntityResolverContext(
                $salesChannelContext,
                $request,
                static::createStub(EntityDefinition::class),
                $category,
            ),
            static::createStub(ElementDataCollection::class),
        );

        static::assertSame($listing, $slot->getData());
    }

    public function testAnEntityOfAnotherTypeDoesNotHandItsIdToTheReader(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request();

        $foreign = new BlogEntity();
        $foreign->setUniqueIdentifier('blog-id');

        $reader = $this->createMock(BlogListingReader::class);
        $reader->expects($this->once())
            ->method('listing')
            ->with(BlogListingStruct::LAYOUT_CLASSIC, null, 1, $salesChannelContext)
            ->willReturn(new BlogListingStruct());

        (new BlogListingCmsElementResolver($reader))->enrich(
            new CmsSlotEntity(),
            new EntityResolverContext(
                $salesChannelContext,
                $request,
                static::createStub(EntityDefinition::class),
                $foreign,
            ),
            static::createStub(ElementDataCollection::class),
        );
    }
}
