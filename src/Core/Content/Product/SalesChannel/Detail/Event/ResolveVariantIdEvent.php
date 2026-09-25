<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Detail\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ResolveVariantIdEvent extends Event implements ShopwellSalesChannelEvent
{
    public function __construct(
        private readonly string $productId,
        private ?string $resolvedVariantId,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setResolvedVariantId(?string $resolvedVariantId): void
    {
        $this->resolvedVariantId = $resolvedVariantId;
    }

    public function getResolvedVariantId(): ?string
    {
        return $this->resolvedVariantId;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
