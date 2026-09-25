<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
#[Package('checkout')]
class QuantityItem extends LineItem
{
    public function __construct(
        float $price,
        TaxRuleCollection $taxes,
        bool $good = true,
        int $quantity = 1
    ) {
        parent::__construct(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, null, $quantity);

        $this->priceDefinition = new QuantityPriceDefinition($price, $taxes, $quantity);
        $this->setGood($good);
    }
}
