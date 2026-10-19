<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpCreateRoute;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
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
#[CoversClass(WalletTopUpCreateRoute::class)]
class WalletTopUpCreateRouteTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testItCreatesTheTopUpForTheLoggedInCustomer(): void
    {
        $context = $this->salesChannelContext();
        $customer = $this->customer();

        $topUpService = $this->createMock(WalletTopUpService::class);
        $topUpService->expects($this->once())
            ->method('create')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                'CNY',
                200.0,
                'alipay_h5',
                'https://shop.test/account/wallet',
                '10.0.0.1',
                'web',
                $context->getContext(),
            )
            ->willReturn([
                'id' => 'top-up-id',
                'amount' => 200.0,
                'currency' => 'CNY',
                'payment_no' => 'PAY-1',
                'channel' => 'alipay_h5',
                'params' => ['url' => 'https://pay.test'],
            ]);

        $route = new WalletTopUpCreateRoute($topUpService, new ExtensionDispatcher(new EventDispatcher()));

        $response = $route->create($this->request(), $context, $customer);

        static::assertSame('PAY-1', $response->getTopUp()->get('payment_no'));
        static::assertSame(200.0, $response->getTopUp()->get('amount'));
    }

    public function testItFallsBackToTheAccountPageAsReturnUrl(): void
    {
        $context = $this->salesChannelContext();

        $topUpService = $this->createMock(WalletTopUpService::class);
        $topUpService->expects($this->once())
            ->method('create')
            ->with(
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                'http://localhost/account/wallet',
                static::anything(),
                null,
                static::anything(),
            )
            ->willReturn(['id' => 'top-up-id', 'amount' => 1.0, 'currency' => 'CNY', 'payment_no' => 'PAY-1', 'channel' => 'alipay_h5', 'params' => []]);

        $route = new WalletTopUpCreateRoute($topUpService, new ExtensionDispatcher(new EventDispatcher()));

        $request = Request::create('/store-api/wallet/top-up', Request::METHOD_POST, ['amount' => 1, 'channel' => 'alipay_h5']);

        $route->create($request, $context, $this->customer());
    }

    private function request(): Request
    {
        return Request::create('/store-api/wallet/top-up', Request::METHOD_POST, [
            'amount' => 200,
            'channel' => 'alipay_h5',
            'returnUrl' => 'https://shop.test/account/wallet',
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
