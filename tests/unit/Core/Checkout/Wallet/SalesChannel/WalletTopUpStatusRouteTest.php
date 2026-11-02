<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletPresenter;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpStatusRoute;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpStatusRoute::class)]
class WalletTopUpStatusRouteTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TOP_UP_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testItAnswersTheStateOfTheTopUp(): void
    {
        $context = Generator::generateSalesChannelContext();

        $topUp = new CustomerWalletTopUpEntity();
        $topUp->setId(self::TOP_UP_ID);
        $topUp->setAmount(200.0);
        $topUp->setCurrency('CNY');
        $topUp->setState(CustomerWalletTopUpEntity::STATE_OPEN);

        $topUpService = $this->createMock(WalletTopUpService::class);
        $topUpService->expects($this->once())
            ->method('find')
            ->with(self::TOP_UP_ID, self::CUSTOMER_ID, $context->getContext())
            ->willReturn($topUp);

        $route = new WalletTopUpStatusRoute(
            $topUpService,
            new WalletPresenter(),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $response = $route->load(self::TOP_UP_ID, $context, $this->customer());

        static::assertSame(CustomerWalletTopUpEntity::STATE_OPEN, $response->getTopUp()->get('state'));
        static::assertSame(200.0, $response->getTopUp()->get('amount'));
    }

    private function customer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(self::CUSTOMER_ID);

        return $customer;
    }
}
