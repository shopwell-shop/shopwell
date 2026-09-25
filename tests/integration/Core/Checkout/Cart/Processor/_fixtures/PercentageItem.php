<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
#[Package('checkout')]
class PercentageItem extends LineItem
{
    public function __construct(
        int $percentage,
        ?string $id = null
    ) {
        parent::__construct($id ?? Uuid::randomHex(), LineItem::DISCOUNT_LINE_ITEM);

        $this->priceDefinition = new PercentagePriceDefinition($percentage);
        $this->removable = true;
    }
}
