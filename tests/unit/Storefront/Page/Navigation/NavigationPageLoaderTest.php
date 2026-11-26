<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Navigation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Navigation\NavigationPageLoader;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NavigationPageLoader::class)]
class NavigationPageLoaderTest extends TestCase
{
    private const CANONICAL = '124c71d524604ccbad6042edce3ac799/navigation/category';

    public function testLoaderLeavesCanonicalAndTitleCleanBeforeContentRendering(): void
    {
        $metaInformation = $this->load($this->createCategory(), ['p' => '2']);

        static::assertSame(self::CANONICAL, $metaInformation->getCanonical());
        static::assertSame('Seasonal decoration', $metaInformation->getMetaTitle());
        static::assertSame('All seasonal decoration', $metaInformation->getMetaDescription());
    }

    /**
     * @param array<string, string> $query
     */
    private function load(CategoryEntity $category, array $query): MetaInformation
    {
        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturnCallback(static function (): Page {
            $page = new Page();
            $page->setMetaInformation(new MetaInformation());

            return $page;
        });

        $categoryRoute = static::createStub(AbstractCategoryRoute::class);
        $categoryRoute->method('load')->willReturn(new CategoryRouteResponse($category));

        $seoUrlReplacer = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->method('generate')->willReturn(self::CANONICAL);

        $loader = new NavigationPageLoader(
            $genericLoader,
            new EventDispatcher(),
            $categoryRoute,
            $seoUrlReplacer,
            static::createStub(CategoryBreadcrumbBuilder::class),
        );

        $request = new Request($query, [], ['navigationId' => $category->getId()]);
        $metaInformation = $loader->load($request, Generator::generateSalesChannelContext())->getMetaInformation();
        static::assertInstanceOf(MetaInformation::class, $metaInformation);

        return $metaInformation;
    }

    private function createCategory(): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);
        $category->setTranslated([
            'name' => 'Category name',
            'metaTitle' => 'Seasonal decoration',
            'metaDescription' => 'All seasonal decoration',
        ]);

        return $category;
    }
}
