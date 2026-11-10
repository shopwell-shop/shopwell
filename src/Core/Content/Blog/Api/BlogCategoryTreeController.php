<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Api;

use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryTree;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Content\Blog\BlogCategoryTreeControllerTest
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class BlogCategoryTreeController
{
    public function __construct(private readonly BlogCategoryTree $tree)
    {
    }

    #[Route(path: '/api/_action/blog/category/{id}/move', name: 'api.action.blog.category.move', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['blog_category:update']], methods: ['POST'])]
    public function move(string $id, Request $request, Context $context): JsonResponse
    {
        $data = $request->toArray();
        foreach (['parentId', 'afterCategoryId'] as $key) {
            if (!\array_key_exists($key, $data) || ($data[$key] !== null && !\is_string($data[$key]))) {
                throw BlogException::invalidFieldValueType($key, 'uuid|null', get_debug_type($data[$key] ?? null));
            }
        }
        $this->tree->move($id, $data['parentId'], $data['afterCategoryId'], $context);

        return new JsonResponse(['success' => true]);
    }
}
