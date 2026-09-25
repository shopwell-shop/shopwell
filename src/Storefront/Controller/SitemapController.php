<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Content\Sitemap\SalesChannel\SitemapFileRoute;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Sitemap\SitemapPageLoadedHook;
use Shopwell\Storefront\Page\Sitemap\SitemapPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class SitemapController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SitemapPageLoader $sitemapPageLoader,
        private readonly SitemapFileRoute $sitemapFileRoute
    ) {
    }

    #[Route(path: '/sitemap.xml', name: 'frontend.sitemap.xml', defaults: ['_format' => 'xml'], methods: ['GET'])]
    public function sitemapXml(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->sitemapPageLoader->load($request, $context);

        $this->hook(new SitemapPageLoadedHook($page, $context));

        $response = $this->renderStorefront('@Storefront/storefront/page/sitemap/sitemap.xml.twig', ['page' => $page]);
        $response->headers->set('content-type', 'text/xml; charset=utf-8');

        return $response;
    }

    #[Route(path: '/sitemap/{filePath}', name: 'frontend.sitemap.proxy', requirements: ['filePath' => '.+\.xml\.gz'], methods: ['GET'])]
    public function sitemapProxy(SalesChannelContext $context, Request $request, string $filePath): Response
    {
        return $this->sitemapFileRoute->getSitemapFile($request, $context, $filePath);
    }
}
