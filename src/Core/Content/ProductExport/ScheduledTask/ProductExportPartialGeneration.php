<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('inventory')]
class ProductExportPartialGeneration implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $productExportId,
        private readonly string $salesChannelId,
        private readonly int $offset = 0
    ) {
    }

    /**
     * Resume position for the next partial-generation batch (0 starts a fresh run).
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getProductExportId(): string
    {
        return $this->productExportId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
