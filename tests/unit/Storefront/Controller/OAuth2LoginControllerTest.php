<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\Framework\Sso\Struct\OAuth2Identity;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\OAuth2LoginController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2LoginController::class)]
class OAuth2LoginControllerTest extends TestCase
{
    public function testStartRedirectsToTheProviderAndRemembersTheRoundTrip(): void
    {
        $request = $this->createRequest(['redirectTo' => 'frontend.account.home.page']);
        $context = Generator::generateSalesChannelContext();

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('createAuthorizationRedirect')
            ->with('google', 'frontend.oauth2-login.callback', static::isInstanceOf(Context::class))
            ->willReturn(new RedirectResponse('https://accounts.google.com/authorize'));

        $response = $this->createController($loginService, $request)->start($request, $context, 'google');

        static::assertSame('https://accounts.google.com/authorize', self::targetUrl($response));
        static::assertSame('login', $request->getSession()->get('oauth2-login.intent'));
        static::assertSame('frontend.account.home.page', $request->getSession()->get('oauth2-login.redirect'));
    }

    public function testStartSendsTheCustomerBackToTheLoginPageForAnUnavailableChannel(): void
    {
        $request = $this->createRequest();
        $context = Generator::generateSalesChannelContext();

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('createAuthorizationRedirect')
            ->willThrowException(SsoException::oauth2ChannelDisabled('github'));

        $response = $this->createController($loginService, $request)->start($request, $context, 'github');

        static::assertSame('https://shop.test/frontend.account.login.page', self::targetUrl($response));
        static::assertSame(
            ['danger' => ['sso.oauth2.error.notAvailable']],
            self::flashes($request)
        );
    }

    public function testCallbackLogsInTheResolvedCustomer(): void
    {
        $request = $this->createRequest();
        $context = Generator::generateSalesChannelContext();

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('fetchIdentity')
            ->willReturn(new OAuth2Identity('google', 'provider-user', 'jane@example.com', true));
        $loginService->expects($this->once())
            ->method('resolveCustomer')
            ->willReturn('customer-id');

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())
            ->method('loginById')
            ->with('customer-id', $context);

        $response = $this->createController($loginService, $request, $accountService)->callback($request, $context, 'google');

        static::assertSame('https://shop.test/frontend.account.home.page', self::targetUrl($response));
    }

    public function testCallbackBindsTheExternalAccountWhenTheRoundTripWasStartedToBind(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set('oauth2-login.intent', 'bind');

        $customer = new CustomerEntity();
        $customer->setId('customer-id');

        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => $customer]);

        $identity = new OAuth2Identity('google', 'provider-user', 'jane@example.com', true);

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('fetchIdentity')
            ->willReturn($identity);
        $loginService->expects($this->never())->method('resolveCustomer');
        $loginService->expects($this->once())
            ->method('bindToCustomer')
            ->with($identity, 'customer-id', static::isInstanceOf(Context::class));

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->never())->method('loginById');

        $response = $this->createController($loginService, $request, $accountService)->callback($request, $context, 'google');

        static::assertSame('https://shop.test/frontend.account.home.page', self::targetUrl($response));
    }

    public function testCallbackReportsAFailedLogin(): void
    {
        $request = $this->createRequest();
        $context = Generator::generateSalesChannelContext();

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('fetchIdentity')
            ->willThrowException(SsoException::oauth2IdentityMissing('google'));

        $this->createController($loginService, $request)->callback($request, $context, 'google');

        static::assertSame(
            ['danger' => ['sso.oauth2.error.failed']],
            self::flashes($request)
        );
    }

    public function testCallbackIgnoresARedirectTargetOutsideTheStorefront(): void
    {
        $request = $this->createRequest(['redirectTo' => 'https://evil.test/phish']);
        $context = Generator::generateSalesChannelContext();

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('createAuthorizationRedirect')
            ->willReturn(new RedirectResponse('https://accounts.google.com/authorize'));

        $controller = $this->createController($loginService, $request);
        $controller->start($request, $context, 'google');

        static::assertNull($request->getSession()->get('oauth2-login.redirect'));
    }

    private static function targetUrl(Response $response): string
    {
        static::assertInstanceOf(RedirectResponse::class, $response);

        return $response->getTargetUrl();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function flashes(Request $request): array
    {
        $session = $request->getSession();
        static::assertInstanceOf(Session::class, $session);

        return $session->getFlashBag()->peekAll();
    }

    /**
     * @param array<string, string> $query
     */
    private function createRequest(array $query = []): Request
    {
        $request = new Request($query);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function createController(
        OAuth2LoginService $loginService,
        Request $request,
        ?AccountService $accountService = null,
    ): OAuth2LoginController {
        $controller = new OAuth2LoginController(
            $loginService,
            $accountService ?? static::createStub(AccountService::class),
        );

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route): string => 'https://shop.test/' . $route
        );

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => $id
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('translator', $translator);
        $container->set('request_stack', $requestStack);
        $container->set('event_dispatcher', new EventDispatcher());

        $controller->setContainer($container);

        return $controller;
    }
}
