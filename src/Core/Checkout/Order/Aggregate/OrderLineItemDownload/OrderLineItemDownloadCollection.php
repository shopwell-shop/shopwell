<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderLineItemDownload;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderLineItemDownloadEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderLineItemDownloadCollection extends EntityCollection
{
    public function filterByOrderLineItemId(string $id): self
    {
        return $this->filter(static fn (OrderLineItemDownloadEntity $orderLineItemDownloadEntity) => $orderLineItemDownloadEntity->getOrderLineItemId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'order_line_item_download_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemDownloadEntity::class;
    }
}
