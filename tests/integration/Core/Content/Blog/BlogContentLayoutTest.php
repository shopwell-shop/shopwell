<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutEntity;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\ContentSystemLayoutProvisioner;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class BlogContentLayoutTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testBlogPresetsProvisionAgainstTheNativeRootContext(): void
    {
        $context = Context::createDefaultContext();
        $provisioner = static::getContainer()->get(ContentSystemLayoutProvisioner::class);
        $repository = static::getContainer()->get('content_layout.repository');

        foreach (['Sw:BlogPost' => true, 'Sw:BlogVideo' => false] as $preset => $toc) {
            $id = $provisioner->provisionFromPreset('Shared ' . $preset, $preset, 'blog', $context);
            $layout = $repository->search(new Criteria([$id]), $context)->first();
            static::assertInstanceOf(ContentLayoutEntity::class, $layout);
            static::assertSame('blog', $layout->getRootSource());
            static::assertSame('Sw:Blog:Article', $layout->getLayout()[0]->component);
            static::assertSame($toc, $layout->getLayout()[0]->property('showTableOfContents')?->asBool());
            static::assertArrayHasKey('blogDetail', $layout->getLayout()[0]->contextDefinitions->getAllConsumers());
        }
    }

    public function testListingPresetUsesStandaloneBlogContext(): void
    {
        $context = Context::createDefaultContext();
        $id = static::getContainer()->get(ContentSystemLayoutProvisioner::class)->provisionFromPreset('Shared listing', 'Sw:BlogListing', 'none', $context);
        $layout = static::getContainer()->get('content_layout.repository')->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);
        static::assertSame('none', $layout->getRootSource());
        static::assertSame('Sw:Blog:Feed', $layout->getLayout()[0]->component);
        static::assertSame('blog_listing', $layout->getLayout()[0]->dataRequirements['listing']->source);
    }

    public function testSharedListingResolvesTheCurrentCategoryThroughTheContentPipeline(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $otherBlogId = Uuid::randomHex();
        static::getContainer()->get('blog_category.repository')->create([['id' => $categoryId, 'name' => 'Guides', 'slug' => $categoryId, 'active' => true]], $context);
        static::getContainer()->get('blog.repository')->create([
            ['id' => $blogId, 'type' => 'post', 'active' => true, 'title' => 'Guide', 'slug' => $blogId, 'categories' => [['blogCategoryId' => $categoryId]]],
            ['id' => $otherBlogId, 'type' => 'post', 'active' => true, 'title' => 'News', 'slug' => $otherBlogId],
        ], $context);
        $id = static::getContainer()->get(ContentSystemLayoutProvisioner::class)->provisionFromPreset('Contextual listing', 'Sw:BlogListing', 'none', $context);
        $channel = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $route = static::getContainer()->get(AbstractContentRoute::class);

        $response = $route->load('/layout/' . $id, new Request(['categoryId' => $categoryId]), $channel);
        static::assertInstanceOf(ContentRouteResponse::class, $response);
        $listing = $response->getContentPage()->elements[0]->properties['listing'];
        static::assertInstanceOf(BlogListingStruct::class, $listing);
        static::assertSame([$blogId], array_values($listing->getBlogs()?->getIds() ?? []));

        $homeResponse = $route->load('/layout/' . $id, new Request(['categoryId' => '']), $channel);
        static::assertInstanceOf(ContentRouteResponse::class, $homeResponse);
        $homeListing = $homeResponse->getContentPage()->elements[0]->properties['listing'];
        static::assertInstanceOf(BlogListingStruct::class, $homeListing);
        static::assertNotNull($homeListing->getBlogs());
        static::assertContains($blogId, $homeListing->getBlogs()->getIds());
        static::assertContains($otherBlogId, $homeListing->getBlogs()->getIds());
    }

    public function testSharedLayoutCanBeAssignedThroughTheBlogAggregate(): void
    {
        $context = Context::createDefaultContext();
        $blogId = Uuid::randomHex();
        $layoutId = static::getContainer()->get(ContentSystemLayoutProvisioner::class)->provisionFromPreset('Shared article', 'Sw:BlogPost', 'blog', $context);
        static::getContainer()->get('blog.repository')->create([[
            'id' => $blogId,
            'title' => 'An article',
            'slug' => $blogId,
            'contentLayouts' => [['contentLayoutId' => $layoutId, 'salesChannelId' => null]],
        ]], $context);

        $criteria = (new Criteria([$layoutId]))->addAssociation('blogContentLayouts');
        $layout = static::getContainer()->get('content_layout.repository')->search($criteria, $context)->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);
        $assignments = $layout->getExtension('blogContentLayouts');
        static::assertInstanceOf(BlogContentLayoutCollection::class, $assignments);
        $assignment = $assignments->first();
        static::assertInstanceOf(BlogContentLayoutEntity::class, $assignment);
        static::assertSame($blogId, $assignment->getBlogId());
        static::assertNull($assignment->getSalesChannelId());
    }
}
