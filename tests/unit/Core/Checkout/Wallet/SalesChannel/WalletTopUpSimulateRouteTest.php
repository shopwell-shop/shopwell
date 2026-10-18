<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpSimulateRoute;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpSimulateRoute::class)]
class WalletTopUpSimulateRouteTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TOP_UP_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testItSettlesTheTopUpOfTheCustomer(): void
    {
        $context = Generator::generateSalesChannelContext();

        $topUpService = $this->createMock(WalletTopUpService::class);
        $topUpService->expects($this->once())
            ->method('simulate')
            ->with(self::TOP_UP_ID, self::CUSTOMER_ID, $context->getContext())
            ->willReturn([
                'id' => self::TOP_UP_ID,
                'amount' => 200.0,
                'currency' => 'CNY',
                'state' => CustomerWalletTopUpEntity::STATE_CREDITED,
            ]);

        $route = new WalletTopUpSimulateRoute($topUpService, new ExtensionDispatcher(new EventDispatcher()));

        $response = $route->simulate(self::TOP_UP_ID, $context, $this->customer());

        static::assertSame(CustomerWalletTopUpEntity::STATE_CREDITED, $response->getTopUp()->get('state'));
    }

    private function customer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(self::CUSTOMER_ID);

        return $customer;
    }
}
