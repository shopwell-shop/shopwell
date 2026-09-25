<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Price;

use Shopwell\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopwell\Core\Content\Product\Hook\Pricing\ProductPricingHook;
use Shopwell\Core\Content\Product\Hook\Pricing\ProductProxy;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class AppScriptProductPriceCalculator extends AbstractProductPriceCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductPriceCalculator $decorated,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly ScriptPriceStubs $priceStubs
    ) {
    }

    public function getDecorated(): AbstractProductPriceCalculator
    {
        return $this->decorated;
    }

    public function calculate(iterable $products, SalesChannelContext $context): void
    {
        $this->decorated->calculate($products, $context);

        $proxies = [];
        foreach ($products as $product) {
            $proxies[$product->get('id')] = new ProductProxy($product, $context, $this->priceStubs);
        }

        $this->scriptExecutor->execute(new ProductPricingHook($proxies, $context));
    }
}
