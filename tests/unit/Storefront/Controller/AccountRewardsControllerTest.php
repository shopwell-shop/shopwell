<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRedeemRoute;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\AccountRewardsController;
use Shopwell\Storefront\Page\Account\Rewards\AccountRewardsPage;
use Shopwell\Storefront\Page\Account\Rewards\AccountRewardsPageLoader;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountRewardsController::class)]
class AccountRewardsControllerTest extends TestCase
{
    public function testReturnsTheCurrentSnapshot(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $page = new AccountRewardsPage();
        $page->setRewards(new ArrayStruct(['balance' => 10]));
        $loader = $this->createMock(AccountRewardsPageLoader::class);
        $loader->expects($this->once())->method('load')->with($request, $context, $customer)->willReturn($page);
        $settings = $this->createMock(LoyaltySettings::class);
        $settings->expects($this->once())->method('isEnabled')->with($context->getSalesChannelId())->willReturn(true);
        $controller = new AccountRewardsController($loader, static::createStub(LoyaltyRedeemRoute::class), $settings);
        $controller->setContainer(new Container());
        $result = $controller->detail($request, $context, $customer);
        static::assertSame(['success' => true, 'rewards' => ['balance' => 10]], json_decode((string) $result->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testDisabledProgrammeDoesNotLoadCustomerData(): void
    {
        $loader = $this->createMock(AccountRewardsPageLoader::class);
        $loader->expects($this->never())->method('load');
        $settings = static::createStub(LoyaltySettings::class);
        $settings->method('isEnabled')->willReturn(false);
        $controller = new AccountRewardsController($loader, static::createStub(LoyaltyRedeemRoute::class), $settings);
        $this->expectException(NotFoundHttpException::class);
        $controller->detail(new Request(), Generator::generateSalesChannelContext(), new CustomerEntity());
    }

    public function testPreservesDomainErrorsInTheJsonEnvelope(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $cart = new Cart('token');
        $error = LoyaltyException::invalidPoints(-1);
        $route = $this->createMock(LoyaltyRedeemRoute::class);
        $route->expects($this->once())->method('redeem')->with($request, $cart, $context, $customer)->willThrowException($error);
        $controller = new AccountRewardsController(static::createStub(AccountRewardsPageLoader::class), $route, static::createStub(LoyaltySettings::class));
        $controller->setContainer(new Container());
        $result = $controller->redeemPoints($request, $cart, $context, $customer);
        static::assertSame(400, $result->getStatusCode());
        static::assertSame(['success' => false, 'errorCode' => $error->getErrorCode()], json_decode((string) $result->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
