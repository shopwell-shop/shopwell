<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Events;

use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductListingResolvePreviewEvent extends NestedEvent implements ShopwellSalesChannelEvent
{
    /**
     * @param array<string> $mapping
     */
    public function __construct(
        protected SalesChannelContext $context,
        protected Criteria $criteria,
        protected array $mapping,
        protected bool $hasOptionFilter
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    /**
     * @return array<string>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function replace(string $originalId, string $newId): void
    {
        if (!\array_key_exists($originalId, $this->mapping)) {
            throw ProductException::originalIdNotFound($originalId);
        }

        $this->mapping[$originalId] = $newId;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function hasOptionFilter(): bool
    {
        return $this->hasOptionFilter;
    }
}
