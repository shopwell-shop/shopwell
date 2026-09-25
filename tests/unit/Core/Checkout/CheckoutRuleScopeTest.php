<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutRuleScope::class)]
class CheckoutRuleScopeTest extends TestCase
{
    public function testConstruct(): void
    {
        $scope = new CheckoutRuleScope($context = Generator::generateSalesChannelContext());

        static::assertSame($context, $scope->getSalesChannelContext());
        static::assertSame($context->getContext(), $scope->getContext());
    }
}
