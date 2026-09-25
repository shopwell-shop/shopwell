<?php declare(strict_types=1);

namespace Shopwell\Storefront\Event;

use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Content\Property\PropertyGroupCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SwitchBuyBoxVariantEvent extends Event implements ShopwellSalesChannelEvent
{
    public function __construct(
        private readonly string $elementId,
        private readonly SalesChannelProductEntity $product,
        private readonly ?PropertyGroupCollection $configurator,
        private readonly Request $request,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getElementId(): string
    {
        return $this->elementId;
    }

    public function getProduct(): SalesChannelProductEntity
    {
        return $this->product;
    }

    public function getConfigurator(): ?PropertyGroupCollection
    {
        return $this->configurator;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
