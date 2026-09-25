<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Transaction;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class TransactionProcessor
{
    public function process(Cart $cart, SalesChannelContext $context): TransactionCollection
    {
        $price = $cart->getPrice()->getTotalPrice();

        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    $price,
                    $price,
                    $cart->getPrice()->getCalculatedTaxes(),
                    $cart->getPrice()->getTaxRules()
                ),
                $context->getPaymentMethod()->getId()
            ),
        ]);
    }
}
