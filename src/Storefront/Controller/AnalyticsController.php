<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Content\Breadcrumb\BreadcrumbException;
use Shopwell\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopwell\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Controller\Exception\StorefrontException;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\System\SalesChannel\SalesChannelAnalyticsLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AnalyticsController extends StorefrontController
{
    public function __construct(
        private readonly AbstractBreadcrumbRoute $breadcrumbRoute,
        private readonly SalesChannelAnalyticsLoader $analyticsLoader,
    ) {
    }

    /**
     * The category names of the product breadcrumb, loaded on demand so product elements do not carry them.
     */
    #[Route(
        path: '/widgets/analytics/product-categories',
        name: 'frontend.analytics.product-categories',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET]
    )]
    public function productCategories(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$this->analyticsLoader->isActive($context)) {
            throw StorefrontException::analyticsNotActive();
        }

        $productId = $request->query->getString('productId');

        if (!Uuid::isValid($productId)) {
            throw RoutingException::invalidRequestParameter('productId');
        }

        // the Store API route reads the product from the `id` attribute and the kind from `type`
        $breadcrumbRequest = $request->duplicate(['type' => 'product']);
        $breadcrumbRequest->attributes->set('id', $productId);

        try {
            $breadcrumbs = $this->breadcrumbRoute
                ->load($breadcrumbRequest, $context)
                ->getBreadcrumbCollection();
        } catch (BreadcrumbException $exception) {
            // a product without a category in the sales channel has no breadcrumb, which is a valid answer
            if ($exception->getErrorCode() === BreadcrumbException::BREADCRUMB_CATEGORY_NOT_FOUND) {
                return $this->json([]);
            }

            throw $exception;
        }

        return $this->json(array_values($breadcrumbs->map(static fn (Breadcrumb $breadcrumb) => $breadcrumb->name)));
    }
}
