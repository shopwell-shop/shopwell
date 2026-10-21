<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Customer\Service\SmsLoginService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopwell\Storefront\Controller\SmsLoginController;
use Shopwell\Tests\Unit\Storefront\Controller\Stub\SmsLoginControllerStub;
use Symfony\Component\HttpFoundation\Request;

/**
 * The controller owns two decisions and nothing else: who is allowed through, and what the caller is
 * told. The important half of the second one is that the answer never depends on whether a number
 * belongs to a customer - a wrong code, an unknown number and a throttled caller all read the same.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SmsLoginController::class)]
class SmsLoginControllerTest extends TestCase
{
    private const SNIPPET_DISABLED = 'account.smsLoginDisabled';

    private const SNIPPET_CODE_SENT = 'account.smsLoginCodeSent';

    private const SNIPPET_THROTTLED = 'account.smsLoginThrottled';

    private const SNIPPET_CODE_INVALID = 'account.loginSmsCodeInvalid';

    private SmsLoginService&Stub $smsLoginService;

    private AccountService&Stub $accountService;

    private StorefrontCartFacade&Stub $cartFacade;

    private RateLimiter&Stub $rateLimiter;

    private SystemConfigService&Stub $systemConfigService;

    private SmsLoginControllerStub $controller;

    protected function setUp(): void
    {
        $this->smsLoginService = static::createStub(SmsLoginService::class);
        $this->accountService = static::createStub(AccountService::class);
        $this->cartFacade = static::createStub(StorefrontCartFacade::class);
        $this->rateLimiter = static::createStub(RateLimiter::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);

        $this->controller = $this->createController();
    }

    public function testASendCodeRequestOfALoggedInCustomerIsAnsweredWithoutANewCode(): void
    {
        $context = $this->createContext($this->createCustomer());
        $request = new Request();

        $smsLoginService = $this->createMock(SmsLoginService::class);
        $smsLoginService->expects($this->never())->method('sendCode');

        $controller = $this->createController(smsLoginService: $smsLoginService);

        $response = $controller->sendCode($request, $this->createDataBag(), $context);

        static::assertNotNull($controller->recorder()->actionResponseRequest);
        static::assertSame([], $controller->recorder()->flashBag, 'A logged in customer sees no message about a code.');
        static::assertSame('action response', $response->getContent());
    }

    public function testASendCodeRequestIsRefusedWhenTheShopSwitchedTheLoginOff(): void
    {
        $context = $this->createContext(null);
        $request = new Request();

        $smsLoginService = $this->createMock(SmsLoginService::class);
        $smsLoginService->expects($this->never())->method('sendCode');
        $smsLoginService->expects($this->never())->method('acquireSendSlot');

        $systemConfigService = $this->createStub(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(false);

        $controller = $this->createController(
            smsLoginService: $smsLoginService,
            systemConfigService: $systemConfigService,
        );

        $controller->sendCode($request, $this->createDataBag(), $context);

        static::assertSame([self::SNIPPET_DISABLED], $controller->recorder()->flashBag['danger'] ?? []);
        static::assertSame(['frontend.account.login.page'], array_keys($controller->recorder()->redirected));
    }

    public function testTheLoginIsOnUntilAnAdministratorSwitchesItOff(): void
    {
        $context = $this->createContext(null);

        $smsLoginService = $this->createMock(SmsLoginService::class);
        $smsLoginService->method('acquireSendSlot')->willReturn(true);
        $smsLoginService->expects($this->once())->method('sendCode');

        $systemConfigService = $this->createStub(SystemConfigService::class);
        // a fresh installation has no value for the setting, and that must not close the door
        $systemConfigService->method('get')->willReturn(null);

        $controller = $this->createController(
            smsLoginService: $smsLoginService,
            systemConfigService: $systemConfigService,
        );

        $controller->sendCode(new Request(), $this->createDataBag(), $context);

        static::assertSame([self::SNIPPET_CODE_SENT], $controller->recorder()->flashBag['success'] ?? []);
    }

    public function testAThrottledSendCodeRequestSendsNoCode(): void
    {
        $context = $this->createContext(null);

        $smsLoginService = $this->createMock(SmsLoginService::class);
        $smsLoginService->expects($this->never())->method('sendCode');

        $rateLimiter = $this->createStub(RateLimiter::class);
        $rateLimiter->method('ensureAccepted')
            ->willThrowException(new RateLimitExceededException(time() + 30));

        $controller = $this->createController(
            smsLoginService: $smsLoginService,
            rateLimiter: $rateLimiter,
        );

        $controller->sendCode(new Request(), $this->createDataBag(), $context);

        static::assertSame([self::SNIPPET_THROTTLED], $controller->recorder()->flashBag['warning'] ?? []);
    }

    public function testASecondCodeIsNotSentWithinTheResendInterval(): void
    {
        $context = $this->createContext(null);

        $smsLoginService = $this->createMock(SmsLoginService::class);
        $smsLoginService->method('acquireSendSlot')->willReturn(false);
        $smsLoginService->method('resendInterval')->willReturn(30);
        $smsLoginService->expects($this->never())->method('sendCode');

        $controller = $this->createController(smsLoginService: $smsLoginService);

        $controller->sendCode(new Request(), $this->createDataBag(), $context);

        static::assertSame([self::SNIPPET_THROTTLED], $controller->recorder()->flashBag['warning'] ?? []);
    }

    public function testTheSendCodeRequestKeepsTheNumberForTheNextAttempt(): void
    {
        $context = $this->createContext(null);

        $smsLoginService = $this->createStub(SmsLoginService::class);
        $smsLoginService->method('acquireSendSlot')->willReturn(true);

        $controller = $this->createController(smsLoginService: $smsLoginService);

        $controller->sendCode(new Request(), $this->createDataBag('+4912345'), $context);

        $redirected = $controller->recorder()->redirected['frontend.account.login.page'][0] ?? [];

        static::assertSame('+4912345', $redirected['parameters']['phone'] ?? null);
    }

    public function testALoginWithAWrongCodeDoesNotLogTheCustomerIn(): void
    {
        $context = $this->createContext(null);

        $smsLoginService = $this->createStub(SmsLoginService::class);
        $smsLoginService->method('verifyCode')->willReturn(null);

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->never())->method('loginById');

        $controller = $this->createController(
            smsLoginService: $smsLoginService,
            accountService: $accountService,
        );

        $controller->login(new Request(), $this->createDataBag(code: '000000'), $context);

        static::assertSame([self::SNIPPET_CODE_INVALID], $controller->recorder()->flashBag['danger'] ?? []);
    }

    public function testAThrottledLoginAnswersExactlyLikeAWrongCode(): void
    {
        $context = $this->createContext(null);

        $smsLoginService = $this->createMock(SmsLoginService::class);
        $smsLoginService->expects($this->once())->method('logThrottledVerification');
        $smsLoginService->expects($this->never())->method('verifyCode');

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->never())->method('loginById');

        $rateLimiter = $this->createStub(RateLimiter::class);
        $rateLimiter->method('ensureAccepted')
            ->willThrowException(new RateLimitExceededException(time() + 30));

        $controller = $this->createController(
            smsLoginService: $smsLoginService,
            accountService: $accountService,
            rateLimiter: $rateLimiter,
        );

        $controller->login(new Request(), $this->createDataBag(code: '000000'), $context);

        static::assertSame(
            [self::SNIPPET_CODE_INVALID],
            $controller->recorder()->flashBag['danger'] ?? [],
            'A caller that could tell the throttling from a wrong code would learn whether a number exists.'
        );
    }

    public function testACustomerThatVanishedBeforeTheLoginIsToldTheCodeIsWrong(): void
    {
        $context = $this->createContext(null);
        $customer = $this->createCustomer();

        $smsLoginService = $this->createStub(SmsLoginService::class);
        $smsLoginService->method('verifyCode')->willReturn($customer);

        $accountService = $this->createStub(AccountService::class);
        $accountService->method('loginById')->willThrowException(new BadCredentialsException());

        $controller = $this->createController(
            smsLoginService: $smsLoginService,
            accountService: $accountService,
        );

        $controller->login(new Request(), $this->createDataBag(code: '123456'), $context);

        static::assertSame([self::SNIPPET_CODE_INVALID], $controller->recorder()->flashBag['danger'] ?? []);
    }

    public function testASuccessfulLoginUsesTheContextTokenOfTheAccountService(): void
    {
        $context = $this->createContext(null);
        $customer = $this->createCustomer();
        $cart = new Cart('token');

        $smsLoginService = $this->createStub(SmsLoginService::class);
        $smsLoginService->method('verifyCode')->willReturn($customer);

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())
            ->method('loginById')
            ->with($customer->getId(), $context)
            ->willReturn('token');

        $cartFacade = $this->createMock(StorefrontCartFacade::class);
        $cartFacade->expects($this->once())
            ->method('get')
            ->with('token', $context)
            ->willReturn($cart);

        $controller = $this->createController(
            smsLoginService: $smsLoginService,
            accountService: $accountService,
            cartFacade: $cartFacade,
        );

        $controller->login(new Request(), $this->createDataBag(code: '123456'), $context);

        static::assertSame([], $controller->recorder()->flashBag);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function redirectTargetProvider(): iterable
    {
        yield 'a storefront route is followed' => ['frontend.checkout.confirm.page', 'action'];
        yield 'no target goes home' => ['', 'redirect'];
        yield 'a route outside the storefront is not followed' => ['admin.dashboard.index', 'redirect'];
    }

    #[DataProvider('redirectTargetProvider')]
    public function testTheCustomerIsSentToTheTargetTheFormCarried(string $redirectTo, string $expected): void
    {
        $context = $this->createContext(null);
        $customer = $this->createCustomer();

        $smsLoginService = $this->createStub(SmsLoginService::class);
        $smsLoginService->method('verifyCode')->willReturn($customer);

        $accountService = $this->createStub(AccountService::class);
        $accountService->method('loginById')->willReturn('token');

        $cartFacade = $this->createStub(StorefrontCartFacade::class);
        $cartFacade->method('get')->willReturn(new Cart('token'));

        $controller = $this->createController(
            smsLoginService: $smsLoginService,
            accountService: $accountService,
            cartFacade: $cartFacade,
        );

        $request = new Request();
        $request->request->set('redirectTo', $redirectTo);

        $controller->login($request, $this->createDataBag(code: '123456'), $context);

        if ($expected === 'action') {
            static::assertNotNull($controller->recorder()->actionResponseRequest);
            static::assertSame([], $controller->recorder()->redirected);

            return;
        }

        static::assertNull($controller->recorder()->actionResponseRequest);
        static::assertSame(['frontend.account.home.page'], array_keys($controller->recorder()->redirected));
    }

    public function testTheRedirectBackToTheFormKeepsTheTargetOfTheCustomer(): void
    {
        $context = $this->createContext(null);

        $smsLoginService = $this->createStub(SmsLoginService::class);
        $smsLoginService->method('acquireSendSlot')->willReturn(false);
        $smsLoginService->method('resendInterval')->willReturn(30);

        $controller = $this->createController(smsLoginService: $smsLoginService);

        $request = new Request();
        $request->request->set('redirectTo', 'frontend.checkout.confirm.page');
        $request->request->set('redirectParameters', '{"foo":"bar"}');

        $controller->sendCode($request, $this->createDataBag('+4912345'), $context);

        $parameters = $controller->recorder()->redirected['frontend.account.login.page'][0]['parameters'] ?? [];

        static::assertSame('frontend.checkout.confirm.page', $parameters['redirectTo'] ?? null);
        static::assertSame('{"foo":"bar"}', $parameters['redirectParameters'] ?? null);
        static::assertSame('+4912345', $parameters['phone'] ?? null);
    }

    public function testAnEmptyRedirectParametersFieldIsNotCarriedOver(): void
    {
        $context = $this->createContext(null);

        $smsLoginService = $this->createStub(SmsLoginService::class);
        $smsLoginService->method('acquireSendSlot')->willReturn(false);
        $smsLoginService->method('resendInterval')->willReturn(30);

        $controller = $this->createController(smsLoginService: $smsLoginService);

        $request = new Request();
        $request->request->set('redirectTo', 'frontend.checkout.confirm.page');
        $request->request->set('redirectParameters', '');

        $controller->sendCode($request, $this->createDataBag('+4912345'), $context);

        $parameters = $controller->recorder()->redirected['frontend.account.login.page'][0]['parameters'] ?? [];

        static::assertArrayNotHasKey(
            'redirectParameters',
            $parameters,
            'An empty field would end up as an empty parameter in the url, so it is left out.'
        );
    }

    private function createController(
        ?SmsLoginService $smsLoginService = null,
        ?AccountService $accountService = null,
        ?StorefrontCartFacade $cartFacade = null,
        ?RateLimiter $rateLimiter = null,
        ?SystemConfigService $systemConfigService = null,
    ): SmsLoginControllerStub {
        return new SmsLoginControllerStub(
            $smsLoginService ?? $this->smsLoginService,
            $accountService ?? $this->accountService,
            $cartFacade ?? $this->cartFacade,
            $rateLimiter ?? $this->rateLimiter,
            $systemConfigService ?? $this->systemConfigService,
        );
    }

    private function createContext(?CustomerEntity $customer): SalesChannelContext
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => $customer]);

        return $context;
    }

    private function createCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setGuest(false);
        $customer->setActive(true);

        return $customer;
    }

    private function createDataBag(string $phone = '+4912345', string $code = '123456'): RequestDataBag
    {
        return new RequestDataBag(['phone' => $phone, 'code' => $code]);
    }
}
