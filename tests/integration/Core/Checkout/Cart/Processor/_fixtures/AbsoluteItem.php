<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
#[Package('checkout')]
class AbsoluteItem extends LineItem
{
    public function __construct(
        float $price,
        ?string $id = null
    ) {
        parent::__construct($id ?? Uuid::randomHex(), LineItem::DISCOUNT_LINE_ITEM);

        $this->priceDefinition = new CurrencyPriceDefinition(new PriceCollection([
            new Price(Defaults::CURRENCY, $price, $price, false),
        ]));
        $this->removable = true;
    }
}
