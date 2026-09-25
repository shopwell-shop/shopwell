<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Stock;

use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
class StockDataCollection
{
    /**
     * @var array<string, StockData>
     */
    private array $stocks = [];

    /**
     * @param array<StockData> $stocks
     */
    public function __construct(array $stocks)
    {
        foreach ($stocks as $stock) {
            $this->add($stock);
        }
    }

    public function add(StockData $stock): void
    {
        $this->stocks[$stock->productId] = $stock;
    }

    public function getStockForProductId(string $productId): ?StockData
    {
        return $this->stocks[$productId] ?? null;
    }

    /**
     * @return array<StockData>
     */
    public function all(): array
    {
        return $this->stocks;
    }
}
