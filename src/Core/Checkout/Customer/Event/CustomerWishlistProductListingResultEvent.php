<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Event;

use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWishlistProductListingResultEvent extends NestedEvent implements ShopwellSalesChannelEvent
{
    final public const EVENT_NAME = 'checkout.customer.wishlist_listing_product_result';

    /**
     * @param EntitySearchResult<ProductCollection> $result
     */
    public function __construct(
        protected Request $request,
        protected EntitySearchResult $result,
        private SalesChannelContext $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->result;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $result
     */
    public function setResult(EntitySearchResult $result): void
    {
        $this->result = $result;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function setSalesChannelContext(SalesChannelContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
