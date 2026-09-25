<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Robots\RobotsPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * we use both API and Storefront route scope here, so that the robots.txt can be accessed
 * via all sales channel domains (+ path routing) + all top level domains without any sales channel domain
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Storefront\Controller\RobotsControllerTest
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID, StorefrontRouteScope::ID], 'auth_required' => false])]
class RobotsController extends StorefrontController
{
    public function __construct(private readonly RobotsPageLoader $robotsPageLoader)
    {
    }

    #[Route(
        path: '/robots.txt',
        name: 'frontend.robots.txt',
        defaults: [
            '_format' => 'txt',
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function robotsTxt(Request $request, Context $context): Response
    {
        $page = $this->robotsPageLoader->load($request, $context);

        $response = $this->render('@Storefront/storefront/page/robots/robots.txt.twig', ['page' => $page]);
        $response->headers->set('content-type', 'text/plain; charset=utf-8');

        return $response;
    }
}
