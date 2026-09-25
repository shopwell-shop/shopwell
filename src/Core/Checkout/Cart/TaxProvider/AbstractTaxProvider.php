<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\TaxProvider;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractTaxProvider
{
    abstract public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult;
}
