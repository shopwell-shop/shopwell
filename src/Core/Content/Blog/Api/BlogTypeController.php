<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Api;

use Shopwell\Core\Content\Blog\Service\BlogTypeRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Content\Blog\BlogTypeControllerTest
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class BlogTypeController
{
    public function __construct(private readonly BlogTypeRegistry $registry)
    {
    }

    #[Route(path: '/api/_action/blog/types', name: 'api.action.blog.types', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['blog:read']], methods: ['GET'])]
    public function types(): JsonResponse
    {
        return new JsonResponse($this->registry->getTypes());
    }
}
