<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipPurchaseRoute;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\AccountMembershipController;
use Shopwell\Storefront\Page\Account\Membership\AccountMembershipPage;
use Shopwell\Storefront\Page\Account\Membership\AccountMembershipPageLoader;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountMembershipController::class)]
class AccountMembershipControllerTest extends TestCase
{
    public function testReturnsTheCurrentSnapshot(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $page = new AccountMembershipPage();
        $page->setMembership(new ArrayStruct(['balance' => 10]));
        $loader = $this->createMock(AccountMembershipPageLoader::class);
        $loader->expects($this->once())->method('load')->with($request, $context, $customer)->willReturn($page);
        $settings = $this->createMock(LoyaltySettings::class);
        $settings->expects($this->once())->method('isEnabled')->with($context->getSalesChannelId())->willReturn(true);
        $controller = new AccountMembershipController($loader, static::createStub(MembershipPurchaseRoute::class), $settings);
        $controller->setContainer(new Container());
        $result = $controller->membershipJson($request, $context, $customer);
        static::assertSame(['success' => true, 'membership' => ['balance' => 10]], json_decode((string) $result->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testDisabledProgrammeDoesNotLoadCustomerData(): void
    {
        $loader = $this->createMock(AccountMembershipPageLoader::class);
        $loader->expects($this->never())->method('load');
        $settings = static::createStub(LoyaltySettings::class);
        $settings->method('isEnabled')->willReturn(false);
        $controller = new AccountMembershipController($loader, static::createStub(MembershipPurchaseRoute::class), $settings);
        $this->expectException(NotFoundHttpException::class);
        $controller->membershipJson(new Request(), Generator::generateSalesChannelContext(), new CustomerEntity());
    }

    public function testPreservesDomainErrorsInTheJsonEnvelope(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $error = LoyaltyException::invalidPoints(-1);
        $route = $this->createMock(MembershipPurchaseRoute::class);
        $route->expects($this->once())->method('purchase')->with($request, $context, $customer)->willThrowException($error);
        $controller = new AccountMembershipController(static::createStub(AccountMembershipPageLoader::class), $route, static::createStub(LoyaltySettings::class));
        $controller->setContainer(new Container());
        $result = $controller->purchase($request, $context, $customer);
        static::assertSame(400, $result->getStatusCode());
        static::assertSame(['success' => false, 'errorCode' => $error->getErrorCode()], json_decode((string) $result->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
