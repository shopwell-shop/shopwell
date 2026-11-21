<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\SalesChannel\CheckoutDraft;
use Shopwell\Core\Checkout\Cart\SalesChannel\CheckoutDraftRouteResponse;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutDraftRouteResponse::class)]
class CheckoutDraftRouteResponseTest extends TestCase
{
    public function testGetDraftReturnsTheDraftTheResponseWasCreatedWith(): void
    {
        $context = Generator::generateSalesChannelContext();

        $draft = new CheckoutDraft(
            cart: new Cart('token'),
            prospectiveCustomer: null,
            shippingMethod: $context->getShippingMethod(),
            paymentMethod: $context->getPaymentMethod(),
            currency: $context->getCurrency(),
            language: $context->getLanguageInfo(),
            shippingMethods: null,
            paymentMethods: null,
            shippingCosts: null,
        );

        static::assertSame($draft, (new CheckoutDraftRouteResponse($draft))->getDraft());
    }
}
