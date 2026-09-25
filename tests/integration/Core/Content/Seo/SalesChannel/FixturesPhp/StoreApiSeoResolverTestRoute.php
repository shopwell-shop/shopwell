<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Seo\SalesChannel\FixturesPhp;

use Shopwell\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class StoreApiSeoResolverTestRoute
{
    public function __construct(
        private readonly AbstractCategoryRoute $categoryRoute,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
    ) {
    }

    #[Route(
        path: '/store-api/test/store-api-seo-resolver/no-auth-required',
        name: 'store-api.test.store_api_seo_resolver.no_auth_required',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function noAuthRequiredAction(Request $request): CategoryRouteResponse
    {
        $salesChannelId = $request->query->get('sales-channel-id');
        \assert($salesChannelId !== null);

        return $this->categoryRoute->load(
            CategoryRoute::HOME,
            $request,
            $this->contextFactory->create(Uuid::randomHex(), $salesChannelId)
        );
    }
}
