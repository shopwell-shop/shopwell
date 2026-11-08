<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\CategoryException;
use Shopwell\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopwell\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\RequestTransformer;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopwell\Storefront\Page\Navigation\NavigationPage;
use Shopwell\Storefront\Page\Navigation\NavigationPageLoadedHook;
use Shopwell\Storefront\Page\Navigation\NavigationPageLoaderInterface;
use Shopwell\Storefront\Pagelet\Footer\FooterPageletLoadedHook;
use Shopwell\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopwell\Storefront\Pagelet\Header\HeaderPageletLoadedHook;
use Shopwell\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Shopwell\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoadedHook;
use Shopwell\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class NavigationController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NavigationPageLoaderInterface $navigationPageLoader,
        private readonly MenuOffcanvasPageletLoaderInterface $offcanvasLoader,
        private readonly HeaderPageletLoaderInterface $headerLoader,
        private readonly FooterPageletLoaderInterface $footerLoader,
        private readonly AbstractCategoryUrlGenerator $categoryUrlGenerator,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
    ) {
    }

    #[Route(
        path: '/',
        name: 'frontend.home.page',
        options: ['seo' => true],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function home(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        $this->hook(new NavigationPageLoadedHook($page, $context));

        $contentPage = $this->loadCategoryContentPage($page, $request, $context);

        return $this->renderStorefront(
            '@Storefront/storefront/page/content/page.html.twig',
            [
                'page' => $page,
                'contentPage' => $contentPage,
                'isNewContentStructure' => true,
            ]
        );
    }

    #[Route(
        path: '/navigation/{navigationId}',
        name: NavigationPageSeoUrlRoute::ROUTE_NAME,
        options: ['seo' => true],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        $this->hook(new NavigationPageLoadedHook($page, $context));

        $category = $page->getCategory();
        \assert($category !== null);

        if ($category->getType() === CategoryDefinition::TYPE_LINK) {
            $host = $request->attributes->get(RequestTransformer::STOREFRONT_URL);
            $urlPlaceholder = $this->categoryUrlGenerator->generate($category, $context->getSalesChannel());

            if (!$urlPlaceholder) {
                throw CategoryException::categoryNotFound($category->getId());
            }

            return new RedirectResponse($this->seoUrlReplacer->replace($urlPlaceholder, $host, $context));
        }

        $contentPage = $this->loadCategoryContentPage($page, $request, $context);

        return $this->renderStorefront(
            '@Storefront/storefront/page/content/page.html.twig',
            [
                'page' => $page,
                'contentPage' => $contentPage,
                'isNewContentStructure' => true,
            ]
        );
    }

    #[Route(
        path: '/content/{path}',
        name: 'frontend.content.layout',
        options: ['seo' => false],
        defaults: ['_httpCache' => true],
        requirements: ['path' => '.+'],
        methods: ['GET'],
    )]
    public function content(string $path, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);
        $contentPage = $this->loadContentPage('/' . ltrim($path, '/'), $request, $context);

        return $this->renderStorefront(
            '@Storefront/storefront/page/content/raw.html.twig',
            [
                'page' => $page,
                'contentPage' => $contentPage,
                'isNewContentStructure' => true,
            ]
        );
    }

    #[Route(
        path: '/widgets/menu/offcanvas',
        name: 'frontend.menu.offcanvas',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET],
    )]
    public function offcanvas(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasLoader->load($request, $context);

        $this->hook(new MenuOffcanvasPageletLoadedHook($page, $context));

        $response = $this->renderStorefront(
            '@Storefront/storefront/layout/navigation/offcanvas/navigation-pagelet.html.twig',
            ['page' => $page]
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(
        path: '/_esi/global/header',
        name: 'frontend.header',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            '_esi' => true,
        ],
        methods: [Request::METHOD_GET],
    )]
    public function header(Request $request, SalesChannelContext $context): Response
    {
        $header = $this->headerLoader->load($request, $context);

        $this->hook(new HeaderPageletLoadedHook($header, $context));

        $headerParameters = $request->query->all('headerParameters');

        if (\array_key_exists('isNewContentStructure', $headerParameters)) {
            return $this->renderStorefront('@Storefront/storefront/page/content/header.html.twig', [
                'header' => $header,
                'headerParameters' => $headerParameters,
                'isNewContentStructure' => true,
            ]);
        }

        return $this->renderStorefront('@Storefront/storefront/layout/header.html.twig', [
            'header' => $header,
            'headerParameters' => $headerParameters,
        ]);
    }

    #[Route(
        path: '/_esi/global/footer',
        name: 'frontend.footer',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            '_esi' => true,
        ],
        methods: [Request::METHOD_GET],
    )]
    public function footer(Request $request, SalesChannelContext $context): Response
    {
        $footer = $this->footerLoader->load($request, $context);

        $this->hook(new FooterPageletLoadedHook($footer, $context));

        return $this->renderStorefront('@Storefront/storefront/layout/footer.html.twig', [
            'footer' => $footer,
            'footerParameters' => $request->query->all()['footerParameters'] ?? [],
        ]);
    }

    private function loadCategoryContentPage(NavigationPage $page, Request $request, SalesChannelContext $context): ?ContentPage
    {
        $category = $page->getCategory();
        \assert($category !== null);

        $categoryId = $category->getId();
        $path = '/category/' . $categoryId;

        return $this->loadContentPage('/' . ltrim($path, '/'), $request, $context);
    }
}
