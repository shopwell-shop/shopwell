<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Processor;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\CartProcessorInterface;
use Shopwell\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\CurrencyPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\FloatComparator;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DiscountCartProcessor implements CartProcessorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PercentagePriceCalculator $percentageCalculator,
        private readonly CurrencyPriceCalculator $currencyCalculator
    ) {
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $items = $original->getLineItems()->filterType(LineItem::DISCOUNT_LINE_ITEM);

        $goods = $toCalculate->getLineItems()->filterGoods();

        foreach ($items as $item) {
            $definition = $item->getPriceDefinition();

            try {
                $price = $this->calculate($definition, $goods, $context);
            } catch (CartException) {
                $original->remove($item->getId());
                $toCalculate->addErrors(new IncompleteLineItemError($item->getId(), 'price'));

                continue;
            }

            if (!$this->validate($price, $goods, $toCalculate)) {
                $original->remove($item->getId());

                continue;
            }

            $item->setPrice($price);
            $item->setShippingCostAware(false);

            $toCalculate->add($item);
        }
    }

    private function validate(CalculatedPrice $price, LineItemCollection $goods, Cart $cart): bool
    {
        if ($goods->count() <= 0) {
            return false;
        }

        if (FloatComparator::greaterThan($price->getTotalPrice(), 0)) {
            return true;
        }

        if (FloatComparator::equals($price->getTotalPrice(), 0)) {
            return false;
        }

        // should not be possible to get negative carts
        $total = $price->getTotalPrice() + $cart->getLineItems()->getPrices()->getTotalPriceAmount();

        return $total >= 0;
    }

    private function calculate(?PriceDefinitionInterface $definition, LineItemCollection $goods, SalesChannelContext $context): CalculatedPrice
    {
        if ($definition instanceof PercentagePriceDefinition) {
            return $this->percentageCalculator->calculate($definition->getPercentage(), $goods->getPrices(), $context);
        }

        if ($definition instanceof CurrencyPriceDefinition) {
            return $this->currencyCalculator->calculate($definition->getPrice(), $goods->getPrices(), $context);
        }

        throw CartException::invalidPriceDefinition();
    }
}
