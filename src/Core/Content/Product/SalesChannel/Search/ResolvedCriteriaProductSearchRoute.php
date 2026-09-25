<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Search;

use Shopwell\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopwell\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopwell\Core\Content\Product\ProductEvents;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class ResolvedCriteriaProductSearchRoute extends AbstractProductSearchRoute
{
    final public const DEFAULT_SEARCH_SORT = 'score';
    final public const STATE = 'search-route-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductSearchRoute $decorated,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CompositeListingProcessor $processor
    ) {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        $criteria->addState(self::STATE);

        // will be handled via processor in next line
        $criteria->setLimit(null);

        $this->processor->prepare($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductSearchCriteriaEvent($request, $criteria, $context),
            ProductEvents::PRODUCT_SEARCH_CRITERIA
        );

        $response = $this->getDecorated()->load($request, $context, $criteria);

        $this->processor->process($request, $response->getListingResult(), $context);

        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $response->getListingResult(), $context),
            ProductEvents::PRODUCT_SEARCH_RESULT
        );

        $response->getListingResult()->addCurrentFilter('search', RequestParamHelper::get($request, 'search'));

        return $response;
    }
}
