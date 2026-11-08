<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Content\LandingPage\LandingPageException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\LandingPage\LandingPageLoadedHook;
use Shopwell\Storefront\Page\LandingPage\LandingPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class LandingPageController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly LandingPageLoader $landingPageLoader)
    {
    }

    #[Route(
        path: '/landingPage/{landingPageId}',
        name: 'frontend.landing.page',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET]
    )]
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->landingPageLoader->load($request, $context);

        $landingPage = $page->getLandingPage();
        if ($landingPage === null) {
            throw LandingPageException::notFound($request->attributes->getString('landingPageId'));
        }

        $this->hook(new LandingPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/content/page.html.twig', [
            'page' => $page,
            'contentPage' => $this->loadContentPage('/landing-page/' . $landingPage->getId(), $request, $context),
            'isNewContentStructure' => true,
        ]);
    }
}
