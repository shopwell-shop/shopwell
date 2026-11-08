<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopwell\Core\Content\Product\SalesChannel\FindVariant\AbstractFindProductVariantRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopwell\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Storefront\Event\SwitchBuyBoxVariantEvent;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class ContentController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductListingRoute $listingRoute,
        private readonly AbstractProductDetailRoute $productRoute,
        private readonly AbstractProductReviewLoader $productReviewLoader,
        private readonly AbstractFindProductVariantRoute $findVariantRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    #[Route(
        path: '/widgets/content/{id}',
        name: 'frontend.content.page',
        defaults: ['id' => null, 'XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function page(?string $id, Request $request, SalesChannelContext $context): Response
    {
        if (!$id) {
            throw RoutingException::missingRequestParameter('id');
        }

        $response = $this->renderStorefront('@Storefront/storefront/page/content/raw.html.twig', [
            'contentPage' => $this->loadContentPage('/layout/' . $id, $request, $context),
        ]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(
        path: '/page/content/{id}',
        name: 'frontend.content.page.full',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function pageFull(string $id, Request $request, SalesChannelContext $context): Response
    {
        return $this->renderStorefront('@Storefront/storefront/page/content/page.html.twig', [
            'contentPage' => $this->loadContentPage('/layout/' . $id, $request, $context),
            'isNewContentStructure' => true,
        ]);
    }

    /**
     * Route to load the listing filters
     */
    #[Route(
        path: '/widgets/content/navigation/{navigationId}/filter',
        name: 'frontend.content.navigation.filter',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function filter(string $navigationId, Request $request, SalesChannelContext $context): Response
    {
        // Allows to fetch only aggregations over the gateway.
        $request->request->set('only-aggregations', true);

        // Allows to convert all post-filters to filters. This leads to the fact that only aggregation values are returned, which are combinable with the previous applied filters.
        $request->request->set('reduce-aggregations', true);

        $listing = $this->listingRoute
            ->load($navigationId, $request, $context, new Criteria())
            ->getResult();

        $mapped = [];
        foreach ($listing->getAggregations() as $aggregation) {
            $mapped[$aggregation->getName()] = $aggregation;
        }

        $response = new JsonResponse($mapped);

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Route to load the product buy box product config assigned to the provided product id.
     * Product id is required to load the slot config for the buy box
     */
    #[Route(
        path: '/widgets/content/buybox/{productId}/switch',
        name: 'frontend.content.buybox.switch',
        defaults: [
            'productId' => null,
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function switchBuyBoxVariant(string $productId, Request $request, SalesChannelContext $context): Response
    {
        /** @var string $elementId */
        $elementId = $request->query->get('elementId');

        /** @var string[]|null $options */
        $options = json_decode($request->query->get('options', ''), true);

        $variantRequestData = [
            'switchedGroup' => $request->query->get('switched'),
            'options' => $options ?? [],
        ];
        $variantRequest = $request->duplicate($variantRequestData);

        $variantResponse = $this->findVariantRoute->load(
            $productId,
            $variantRequest,
            $context
        );

        $newProductId = $variantResponse->getFoundCombination()->getVariantId();

        $result = $this->productRoute->load($newProductId, $request, $context, new Criteria());
        $product = $result->getProduct();
        $configurator = $result->getConfigurator();

        $reviewTotal = 0;
        if ($this->systemConfigService->getBool('core.listing.showReview', $context->getSalesChannelId())) {
            $reviews = $this->productReviewLoader->load($request, $context, $product->getId(), $product->getParentId());
            $reviewTotal = $reviews->getTotal();
        }

        $event = new SwitchBuyBoxVariantEvent($elementId, $product, $configurator, $request, $context);
        $this->eventDispatcher->dispatch($event);

        $response = $this->renderStorefront('@Storefront/storefront/component/buy-widget/buy-widget.html.twig', [
            'product' => $product,
            'configuratorSettings' => $configurator,
            'totalReviews' => $reviewTotal,
            'elementId' => $elementId,
        ]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }
}
