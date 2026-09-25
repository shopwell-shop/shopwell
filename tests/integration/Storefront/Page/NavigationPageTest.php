<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopwell\Storefront\Page\Navigation\NavigationPageLoader;
use Shopwell\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class NavigationPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItDoesLoadAPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CategoryEntity::class, $page->getCategory());
        static::assertPageEvent(NavigationPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDeniesAccessToInactiveCategoryPage(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $repository = static::getContainer()->get('category.repository');

        $categoryId = $context->getSalesChannel()->getNavigationCategoryId();

        $repository->update([[
            'id' => $categoryId,
            'active' => false,
        ]], $context->getContext());

        $request = new Request([], [], ['navigationId' => $categoryId]);

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $this->expectException(CategoryNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesHaveCanonicalTag(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();
        $seoUrlHandler = static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $metaInformation = $this->getPageLoader()->load($request, $context)->getMetaInformation();
        static::assertNotNull($metaInformation);
        $meta = $metaInformation->getVars();
        $canonical = $meta['canonical'];

        $seoUrl = $seoUrlHandler->replace($canonical, $request->getHost(), $context);

        static::assertSame('/', $seoUrl);
    }

    protected function getPageLoader(): NavigationPageLoader
    {
        return static::getContainer()->get(NavigationPageLoader::class);
    }
}
