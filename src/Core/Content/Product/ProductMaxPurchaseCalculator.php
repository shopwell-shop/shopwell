<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

#[Package('inventory')]
class ProductMaxPurchaseCalculator extends AbstractProductMaxPurchaseCalculator
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getDecorated(): AbstractProductMaxPurchaseCalculator
    {
        throw new DecorationPatternException(self::class);
    }

    public function calculate(Entity $product, SalesChannelContext $context): int
    {
        $fallback = $this->systemConfigService->getInt(
            'core.cart.maxQuantity',
            $context->getSalesChannelId()
        );

        $max = $product->get('maxPurchase') ?? $fallback;

        if ($product->get('isCloseout') && $product->get('stock') < $max) {
            $max = (int) $product->get('stock');
        }

        $steps = $product->get('purchaseSteps') ?? 1;
        $min = $product->get('minPurchase') ?? 1;

        // the amount of times the purchase step is fitting in between min and max added to the minimum
        $max = \floor(($max - $min) / $steps) * $steps + $min;

        return (int) \max($max, 0);
    }
}
