<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopwell\Storefront\Page\Search\SearchPage;
use Shopwell\Storefront\Page\Search\SearchPageLoadedHook;
use Shopwell\Storefront\Page\Search\SearchPageLoader;
use Shopwell\Storefront\Page\Search\SearchWidgetLoadedHook;
use Shopwell\Storefront\Page\Suggest\SuggestPageLoadedHook;
use Shopwell\Storefront\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class SearchController extends StorefrontController
{
    /**
     * @internal
     *
     * @param list<string> $redirectOnSingleHitFields
     */
    public function __construct(
        private readonly SearchPageLoader $searchPageLoader,
        private readonly SuggestPageLoader $suggestPageLoader,
        private readonly AbstractProductSearchRoute $productSearchRoute,
        private readonly array $redirectOnSingleHitFields = ['productNumber', 'ean', 'manufacturerNumber']
    ) {
    }

    #[Route(
        path: '/search',
        name: 'frontend.search.page',
        methods: [Request::METHOD_GET]
    )]
    public function search(SalesChannelContext $context, Request $request): Response
    {
        try {
            $page = $this->searchPageLoader->load($request, $context);

            $response = $this->handleFirstHit($request, $page);

            if ($response !== null) {
                return $response;
            }
        } catch (RoutingException $e) {
            if ($e->getErrorCode() !== RoutingException::MISSING_REQUEST_PARAMETER_CODE) {
                throw $e;
            }

            return $this->forwardToRoute('frontend.home.page');
        }

        $this->hook(new SearchPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/suggest',
        name: 'frontend.search.suggest',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        if (!$request->request->has('no-aggregations')) {
            $request->request->set('no-aggregations', true);
        }

        $page = $this->suggestPageLoader->load($request, $context);

        $this->hook(new SuggestPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/layout/header/search-suggest.html.twig', ['page' => $page]);
    }

    /**
     * Route to load the listing filters
     */
    #[Route(
        path: '/widgets/search',
        name: 'widgets.search.pagelet.v2',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function ajax(Request $request, SalesChannelContext $context): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->searchPageLoader->load($request, $context);

        $this->hook(new SearchWidgetLoadedHook($page, $context));

        $response = $this->renderStorefront('@Storefront/storefront/page/search/search-pagelet.html.twig', ['page' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Route to load the available listing filters
     */
    #[Route(
        path: '/widgets/search/filter',
        name: 'widgets.search.filter',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function filter(Request $request, SalesChannelContext $context): Response
    {
        $term = RequestParamHelper::get($request, 'search');
        if (!$term) {
            throw RoutingException::missingRequestParameter('search');
        }

        // Allows to fetch only aggregations over the gateway.
        $request->request->set('only-aggregations', true);
        // Allows to convert all post-filters to filters. This leads to the fact that only aggregation values are returned, which are combinable with the previous applied filters.
        $request->request->set('reduce-aggregations', true);
        $criteria = new Criteria();
        $criteria->setTitle('search-page');

        $result = $this->productSearchRoute
            ->load($request, $context, $criteria)
            ->getListingResult();
        $mapped = [];

        foreach ($result->getAggregations() as $aggregation) {
            $mapped[$aggregation->getName()] = $aggregation;
        }

        $response = new JsonResponse($mapped);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    private function handleFirstHit(Request $request, SearchPage $page): ?Response
    {
        if ($page->getListing()->getTotal() > 1) {
            return null;
        }

        $product = $page->getListing()->getEntities()->first();
        if (!$product instanceof ProductEntity) {
            return null;
        }

        $search = $request->query->get('search');
        if (!\is_string($search)) {
            return null;
        }

        $search = mb_strtolower(trim($search));
        if ($search === '') {
            return null;
        }

        foreach ($this->redirectOnSingleHitFields as $field) {
            if (!$product->has($field)) {
                continue;
            }

            $value = $product->get($field);
            if (!\is_string($value) || $value === '') {
                continue;
            }

            if ($search === mb_strtolower(trim($value))) {
                return $this->redirectToRoute(ProductPageSeoUrlRoute::ROUTE_NAME, ['productId' => $product->getId()]);
            }
        }

        return null;
    }
}
