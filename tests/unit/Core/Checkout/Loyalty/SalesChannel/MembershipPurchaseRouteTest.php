<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\MembershipPurchaseRoute;
use Shopwell\Core\Checkout\Loyalty\Service\MembershipService;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;
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

        $membershipService = $this->createMock(MembershipService::class);
        $membershipService->expects($this->once())
            ->method('purchase')
            ->with(
                self::CUSTOMER_ID,
                self::PLAN_ID,
                'alipay_h5',
                self::CURRENCY_ID,
                'CNY',
                'https://shop.test/account/loyalty',
                '10.0.0.1',
                'web',
                $context->getContext(),
            )
            ->willReturn([
                'id' => 'order-id',
                'amount' => 199.0,
                'currency' => 'CNY',
                'payment_no' => 'PAY-1',
                'channel' => 'alipay_h5',
                'params' => ['url' => 'https://pay.test'],
            ]);

        $route = new MembershipPurchaseRoute($membershipService, new ExtensionDispatcher(new EventDispatcher()));

        $response = $route->purchase($this->request(), $context, $this->customer());

        static::assertSame('PAY-1', $response->getPurchase()->get('payment_no'));
        static::assertSame(199.0, $response->getPurchase()->get('amount'));
    }

    public function testItFallsBackToTheLoyaltyPageAsReturnUrl(): void
    {
        $context = $this->salesChannelContext();

        $membershipService = $this->createMock(MembershipService::class);
        $membershipService->expects($this->once())
            ->method('purchase')
            ->with(
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                'http://localhost/account/loyalty',
                static::anything(),
                null,
                static::anything(),
            )
            ->willReturn(['id' => 'order-id', 'amount' => 1.0, 'currency' => 'CNY', 'payment_no' => 'PAY-1', 'channel' => 'alipay_h5', 'params' => []]);

        $route = new MembershipPurchaseRoute($membershipService, new ExtensionDispatcher(new EventDispatcher()));

        $request = Request::create('/store-api/membership/purchase', Request::METHOD_POST, ['planId' => self::PLAN_ID, 'channel' => 'alipay_h5']);

        $route->purchase($request, $context, $this->customer());
    }

    private function request(): Request
    {
        return Request::create('/store-api/membership/purchase', Request::METHOD_POST, [
            'planId' => self::PLAN_ID,
            'channel' => 'alipay_h5',
            'returnUrl' => 'https://shop.test/account/loyalty',
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
