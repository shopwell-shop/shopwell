<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractDeleteCustomerRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Controller\AccountProfileController;
use Shopwell\Storefront\Controller\StorefrontController;
use Shopwell\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopwell\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Shopwell\Tests\Unit\Storefront\Controller\Stub\AccountProfileControllerStub;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountProfileController::class)]
class AccountProfileControllerTest extends TestCase
{
    private ChangePasswordRoute&Stub $changePasswordRoute;

    private AccountProfileControllerStub $controller;

    protected function setUp(): void
    {
        $this->changePasswordRoute = static::createStub(ChangePasswordRoute::class);

        $this->controller = new AccountProfileControllerStub(
            static::createStub(AccountOverviewPageLoader::class),
            static::createStub(AccountProfilePageLoader::class),
            static::createStub(AbstractChangeCustomerProfileRoute::class),
            $this->changePasswordRoute,
            static::createStub(AbstractChangeEmailRoute::class),
            static::createStub(AbstractDeleteCustomerRoute::class),
            static::createStub(LoggerInterface::class),
        );
    }

    public function testSavePasswordWithMissingPasswordParam(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('password'));

        $this->controller->savePassword(
            new RequestDataBag(),
            static::createStub(SalesChannelContext::class),
            new CustomerEntity(),
            new Request()
        );
    }

    public function testSavePasswordWithConstraintViolation(): void
    {
        $this->changePasswordRoute->method('change')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );

        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(SalesChannelContext::class),
            new CustomerEntity(),
            new Request()
        );

        static::assertSame('frontend.account.profile.page', $this->controller->forwardToRoute);
        static::assertTrue($this->controller->forwardToRouteAttributes['passwordFormViolation']);
        static::assertInstanceOf(ConstraintViolationException::class, $this->controller->forwardToRouteAttributes['formViolations']);
        static::assertSame(['account.passwordChangeNoSuccess'], $this->controller->flashBag[StorefrontController::DANGER]);
    }

    public function testSavePasswordWithDefaultRedirect(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(SalesChannelContext::class),
            new CustomerEntity(),
            new Request()
        );

        static::assertArrayHasKey('frontend.account.profile.page', $this->controller->redirected);
        static::assertSame(['account.passwordChangeSuccess'], $this->controller->flashBag[StorefrontController::SUCCESS]);
    }

    public function testSavePasswordWithCustomRedirect(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(SalesChannelContext::class),
            new CustomerEntity(),
            new Request([], ['redirectTo' => 'frontend.home.page'])
        );

        static::assertArrayHasKey('frontend.home.page', $this->controller->redirected);
    }

    public function testSavePasswordWithForwardToParam(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(SalesChannelContext::class),
            new CustomerEntity(),
            new Request([], ['forwardTo' => 'frontend.account.home.page'])
        );

        static::assertSame('frontend.account.home.page', $this->controller->forwardToRoute);
    }

    private function passwordDataBag(): RequestDataBag
    {
        return new RequestDataBag(['password' => new RequestDataBag([
            'newPassword' => 'newPassword123',
            'newPasswordConfirm' => 'newPassword123',
            'password' => 'oldPassword',
        ])]);
    }
}
