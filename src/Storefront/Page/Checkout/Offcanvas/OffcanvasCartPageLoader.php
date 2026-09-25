<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Checkout\Offcanvas;

use Shopwell\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class OffcanvasCartPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly StorefrontCartFacade $cartService,
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): OffcanvasCartPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = OffcanvasCartPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->assign(['robots' => 'noindex,follow']);
        }

        $page->setCart($this->cartService->get($salesChannelContext->getToken(), $salesChannelContext));

        $page->setShippingMethods($this->getShippingMethods($request, $salesChannelContext));

        $this->eventDispatcher->dispatch(
            new OffcanvasCartPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getShippingMethods(Request $request, SalesChannelContext $context): ShippingMethodCollection
    {
        $shippingMethodRequest = $request->duplicate(['onlyAvailable' => true]);

        return $this->shippingMethodRoute->load($shippingMethodRequest, $context, new Criteria())->getShippingMethods();
    }
}
