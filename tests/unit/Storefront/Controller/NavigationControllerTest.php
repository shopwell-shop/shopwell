<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopwell\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopwell\Core\Content\Category\Tree\Tree;
use Shopwell\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\NavigationController;
use Shopwell\Storefront\Framework\Routing\RequestTransformer;
use Shopwell\Storefront\Page\Navigation\NavigationPage;
use Shopwell\Storefront\Page\Navigation\NavigationPageLoaderInterface;
use Shopwell\Storefront\Pagelet\Footer\FooterPagelet;
use Shopwell\Storefront\Pagelet\Footer\FooterPageletLoadedHook;
use Shopwell\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopwell\Storefront\Pagelet\Header\HeaderPagelet;
use Shopwell\Storefront\Pagelet\Header\HeaderPageletLoadedHook;
use Shopwell\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Shopwell\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NavigationController::class)]
class NavigationControllerTest extends TestCase
{
    private NavigationPageLoaderInterface&Stub $pageLoader;

    private MenuOffcanvasPageletLoaderInterface&Stub $offCanvasLoader;

    private NavigationControllerTestClass $controller;

    private HeaderPageletLoaderInterface&Stub $headerLoader;

    private FooterPageletLoaderInterface&Stub $footerLoader;

    private AbstractCategoryUrlGenerator $categoryUrlGenerator;

    private SeoUrlPlaceholderHandlerInterface&Stub $seoUrlReplacer;

    protected function setUp(): void
    {
        $this->pageLoader = static::createStub(NavigationPageLoaderInterface::class);
        $this->offCanvasLoader = static::createStub(MenuOffcanvasPageletLoaderInterface::class);
        $this->headerLoader = static::createStub(HeaderPageletLoaderInterface::class);
        $this->footerLoader = static::createStub(FooterPageletLoaderInterface::class);

        $this->seoUrlReplacer = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $this->seoUrlReplacer->method('replace')
            ->willReturnCallback(static fn (string $url) => $url);

        $entityRouteResolver = static::createStub(EntityRouteResolver::class);
        $entityRouteResolver->method('generateSeoUrlPlaceholder')
            ->willReturnCallback(static function (string $entityName, string $primaryKey) {
                return match ($entityName) {
                    'product' => '/product/' . $primaryKey,
                    'category' => '/navigation/' . $primaryKey,
                    'landing_page' => '/landingPage/' . $primaryKey,
                    default => '/' . $entityName,
                };
            });
        $this->categoryUrlGenerator = new CategoryUrlGenerator($entityRouteResolver);

        $this->controller = new NavigationControllerTestClass(
            $this->pageLoader,
            $this->offCanvasLoader,
            $this->headerLoader,
            $this->footerLoader,
            $this->categoryUrlGenerator,
            $this->seoUrlReplacer,
        );
    }

    public function testHomeRendersStorefront(): void
    {
        $this->pageLoader->method('load')
            ->willReturn(new NavigationPage());

        $request = new Request();
        $context = Generator::generateSalesChannelContext();

        $this->controller->home($request, $context);
        static::assertSame('@Storefront/storefront/page/content/index.html.twig', $this->controller->renderStorefrontView);
    }

    public function testIndexRendersStorefront(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_PAGE);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request([
            'navigationId' => Uuid::randomHex(),
        ]);
        $context = Generator::generateSalesChannelContext();

        $this->controller->index($context, $request);
        static::assertSame('@Storefront/storefront/page/content/index.html.twig', $this->controller->renderStorefrontView);
    }

    public static function redirectOnLinkTypeDataProvider(): \Generator
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        yield 'product link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_PRODUCT,
                'internalLink' => $productId,
                'externalLink' => 'This should not be used',
            ],
            'expectedUrl' => '/product/' . $productId,
        ];

        yield 'category link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
                'internalLink' => $categoryId,
                'externalLink' => 'This should not be used',
            ],
            'expectedUrl' => '/navigation/' . $categoryId,
        ];

        yield 'external link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                'internalLink' => 'This should not be used',
                'externalLink' => 'https://example.com',
            ],
            'expectedUrl' => 'https://example.com',
        ];
    }

    /**
     * @param array{linkType: string, internalLink: string, externalLink: string} $data
     */
    #[DataProvider('redirectOnLinkTypeDataProvider')]
    public function testIndexRedirectsOnLinkType(array $data, string $expectedUrl): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType($data['linkType']);
        $category->setInternalLink($data['internalLink']);
        $category->setExternalLink($data['externalLink']);

        $category->setTranslated([
            'linkType' => $data['linkType'],
            'internalLink' => $data['internalLink'],
            'externalLink' => $data['externalLink'],
        ]);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request(
            ['navigationId' => Uuid::randomHex()],
            [],
            [RequestTransformer::STOREFRONT_URL => 'https://example.com'],
        );

        $context = Generator::generateSalesChannelContext();

        $response = $this->controller->index($context, $request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame($expectedUrl, $response->getTargetUrl());
    }

    public function testIndexDoesNotRedirectOnLinkTypeWithoutUrl(): void
    {
        $categoryId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId($categoryId);
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType(CategoryDefinition::LINK_TYPE_PRODUCT);
        $category->setInternalLink(null);

        $category->setTranslated([
            'linkType' => CategoryDefinition::LINK_TYPE_PRODUCT,
            'internalLink' => null,
        ]);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request(
            ['navigationId' => Uuid::randomHex()],
            [],
            [RequestTransformer::STOREFRONT_URL => 'https://example.com'],
        );

        $context = Generator::generateSalesChannelContext();

        $this->expectExceptionObject(new CategoryNotFoundException($categoryId));

        $this->controller->index($context, $request);
    }

    public function testOffcanvasRendersStorefront(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();

        $response = $this->controller->offcanvas($request, $context);
        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
        static::assertSame('@Storefront/storefront/layout/navigation/offcanvas/navigation-pagelet.html.twig', $this->controller->renderStorefrontView);
    }

    public function testHeaderRendersStorefront(): void
    {
        $request = new Request(['headerParameters' => ['foo' => 'bar']]);
        $context = Generator::generateSalesChannelContext();
        $headerPagelet = new HeaderPagelet(new Tree(null, []), new LanguageCollection(), new CurrencyCollection());

        $headerLoader = $this->createMock(HeaderPageletLoaderInterface::class);
        $headerLoader->expects($this->once())->method('load')->with($request, $context)->willReturn($headerPagelet);

        $this->controller = $this->buildController(headerLoader: $headerLoader);
        $this->controller->header($request, $context);
        static::assertSame('@Storefront/storefront/layout/header.html.twig', $this->controller->renderStorefrontView);
        static::assertSame(['foo' => 'bar'], $this->controller->renderStorefrontParameters['headerParameters']);

        static::assertInstanceOf(HeaderPageletLoadedHook::class, $this->controller->calledHook);
        static::assertSame($headerPagelet, $this->controller->calledHook->getPage());
    }

    public function testFooterRendersStorefront(): void
    {
        $request = new Request(['footerParameters' => ['foo' => 'bar']]);
        $context = Generator::generateSalesChannelContext();
        $footerPagelet = new FooterPagelet(null, new CategoryCollection(), new PaymentMethodCollection(), new ShippingMethodCollection());

        $footerLoader = $this->createMock(FooterPageletLoaderInterface::class);
        $footerLoader->expects($this->once())->method('load')->with($request, $context)->willReturn($footerPagelet);

        $this->controller = $this->buildController(footerLoader: $footerLoader);
        $this->controller->footer($request, $context);
        static::assertSame('@Storefront/storefront/layout/footer.html.twig', $this->controller->renderStorefrontView);
        static::assertSame(['foo' => 'bar'], $this->controller->renderStorefrontParameters['footerParameters']);

        static::assertInstanceOf(FooterPageletLoadedHook::class, $this->controller->calledHook);
        static::assertSame($footerPagelet, $this->controller->calledHook->getPage());
    }

    private function buildController(
        ?HeaderPageletLoaderInterface $headerLoader = null,
        ?FooterPageletLoaderInterface $footerLoader = null,
    ): NavigationControllerTestClass {
        return new NavigationControllerTestClass(
            $this->pageLoader,
            $this->offCanvasLoader,
            $headerLoader ?? $this->headerLoader,
            $footerLoader ?? $this->footerLoader,
            $this->categoryUrlGenerator,
            $this->seoUrlReplacer,
        );
    }
}

/**
 * @internal
 */
class NavigationControllerTestClass extends NavigationController
{
    use StorefrontControllerMockTrait;
}
