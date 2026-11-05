<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipPurchaseRoute;
use Shopwell\Core\Checkout\Membership\Service\MembershipPurchaseService;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentContextResolver;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipPurchaseRoute::class)]
class MembershipPurchaseRouteTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const PLAN_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testItBuysThePlanForTheLoggedInCustomer(): void
    {
        $context = $this->salesChannelContext();

        $membershipService = $this->createMock(MembershipPurchaseService::class);
        $membershipService->expects($this->once())
            ->method('purchase')
            ->with(self::CUSTOMER_ID, self::PLAN_ID, 'alipay', self::CURRENCY_ID, new PaymentContext('CNY', new PaymentScene('browser', 'web', 'browser')), 'https://shop.test/account/membership', '10.0.0.1', $context->getContext())
            ->willReturn([
                'id' => 'order-id',
                'amount' => 199.0,
                'currency' => 'CNY',
                'payment_no' => 'PAY-1',
                'channel' => 'alipay_h5',
                'params' => ['url' => 'https://pay.test'],
            ]);

        $route = new MembershipPurchaseRoute($membershipService, new ExtensionDispatcher(new EventDispatcher()), new StorefrontPaymentContextResolver());

        $response = $route->purchase($this->request(), $context, $this->customer());

        static::assertSame('PAY-1', $response->getPurchase()->get('payment_no'));
        static::assertSame(199.0, $response->getPurchase()->get('amount'));
    }

    public function testItFallsBackToTheLoyaltyPageAsReturnUrl(): void
    {
        $context = $this->salesChannelContext();

        $membershipService = $this->createMock(MembershipPurchaseService::class);
        $membershipService->expects($this->once())
            ->method('purchase')
            ->with(static::anything(), static::anything(), static::anything(), static::anything(), static::isInstanceOf(PaymentContext::class), 'http://localhost/account/membership', static::anything(), static::anything())
            ->willReturn(['id' => 'order-id', 'amount' => 1.0, 'currency' => 'CNY', 'payment_no' => 'PAY-1', 'method' => 'alipay', 'params' => []]);

        $route = new MembershipPurchaseRoute($membershipService, new ExtensionDispatcher(new EventDispatcher()), new StorefrontPaymentContextResolver());

        $request = Request::create('/store-api/membership/purchase', Request::METHOD_POST, ['planId' => self::PLAN_ID, 'method' => 'alipay']);

        $route->purchase($request, $context, $this->customer());
    }

    private function request(): Request
    {
        return Request::create('/store-api/membership/purchase', Request::METHOD_POST, [
            'planId' => self::PLAN_ID,
            'method' => 'alipay',
            'returnUrl' => 'https://shop.test/account/membership',
            'device' => 'WEB',
        ], server: ['REMOTE_ADDR' => '10.0.0.1']);
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $currency = new CurrencyEntity();
        $currency->setId(self::CURRENCY_ID);
        $currency->setIsoCode('CNY');
        $currency->setFactor(1.0);

        return Generator::generateSalesChannelContext(currency: $currency);
    }

    private function customer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(self::CUSTOMER_ID);

        return $customer;
    }
}
