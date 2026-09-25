<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
final readonly class AppController
{
    public function __construct(private AppJWTGenerateRoute $appJWTGenerateRoute)
    {
    }

    #[Route(
        path: '/app-system/{name}/generate-token',
        name: 'frontend.app-system.generate-token',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function generateToken(string $name, SalesChannelContext $context): Response
    {
        try {
            return $this->appJWTGenerateRoute->generate($name, $context);
        } catch (AppException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }
}
