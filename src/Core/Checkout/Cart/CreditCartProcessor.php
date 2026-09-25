<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart;

use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CreditCartProcessor implements CartProcessorInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly AbsolutePriceCalculator $calculator)
    {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $lineItems = $original->getLineItems()->filterType(LineItem::CREDIT_LINE_ITEM_TYPE);

        foreach ($lineItems as $lineItem) {
            $definition = $lineItem->getPriceDefinition();

            if (!$definition instanceof AbsolutePriceDefinition) {
                continue;
            }

            $lineItem->setPrice(
                $this->calculator->calculate(
                    $definition->getPrice(),
                    $toCalculate->getLineItems()->getPrices(),
                    $context
                )
            );
            $lineItem->setShippingCostAware(false);

            $toCalculate->add($lineItem);
        }
    }
}
