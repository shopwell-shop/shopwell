<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Rule\Helper;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Rule\LineItemPropertyRule;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CartRuleScopeCase
{
    /**
     * @param LineItem[] $lineItems
     */
    public function __construct(
        public string $description,
        public bool $match,
        public LineItemPropertyRule $rule,
        public array $lineItems
    ) {
    }
}
