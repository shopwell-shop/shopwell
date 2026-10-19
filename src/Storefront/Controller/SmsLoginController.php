<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopwell\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Customer\Service\SmsLoginService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The login by sms: one request asks for a code, the next one answers it.
 *
 * The controller only reads the form, keeps the door closed and says what happened; the code, the
 * number and the customer live in the SmsLoginService. Its one rule is that neither answer may tell
 * whether a number belongs to a customer: the request for a code ends in the same message whether a
 * code was sent or not, and the answer to a code is the same whether the code was wrong, gone or the
 * caller is throttled. The throttling is keyed on the number the caller wrote, so it says nothing
 * about the shop either.
 *
 * @internal
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class SmsLoginController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SmsLoginService $smsLoginService,
        private readonly AccountService $accountService,
        private readonly StorefrontCartFacade $cartFacade,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    #[Route(
        path: '/account/login/sms/code',
        name: 'frontend.account.login.sms-code',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_POST]
    )]
    public function sendCode(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($this->isLoggedIn($context)) {
            return $this->createActionResponse($request);
        }

        $phone = $data->getString('phone');

        try {
            $this->rateLimiter->ensureAccepted(
                RateLimiter::SMS_LOGIN_CODE,
                $this->smsLoginService->rateLimiterKey($phone, $context, $request->getClientIp() ?? '')
            );
        } catch (RateLimitExceededException $exception) {
            $this->addFlash(self::WARNING, $this->trans('account.smsLoginThrottled', ['%seconds%' => $exception->getWaitTime()]));

            return $this->redirectToLoginPage($request, $phone);
        }

        if (!$this->smsLoginService->acquireSendSlot($phone, $context)) {
            $this->addFlash(self::WARNING, $this->trans('account.smsLoginThrottled', ['%seconds%' => SmsLoginService::RESEND_COOLDOWN]));

            return $this->redirectToLoginPage($request, $phone);
        }

        // whether a code was created, whether the number belongs to a customer and whether the
        // gateway accepted the message stays in the log, the customer sees one message for all of it
        $this->smsLoginService->sendCode($phone, $context);

        $this->addFlash(self::SUCCESS, $this->trans('account.smsLoginCodeSent'));

        return $this->redirectToLoginPage($request, $phone);
    }

    #[Route(
        path: '/account/login/sms',
        name: 'frontend.account.login.sms',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_POST]
    )]
    public function login(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($this->isLoggedIn($context)) {
            return $this->createActionResponse($request);
        }

        $phone = $data->getString('phone');

        try {
            $this->rateLimiter->ensureAccepted(
                RateLimiter::SMS_LOGIN_VERIFY,
                $this->smsLoginService->rateLimiterKey($phone, $context, $request->getClientIp() ?? '')
            );
        } catch (RateLimitExceededException) {
            // the answer of a throttled caller is the one of a wrong code: a caller that could tell
            // the two apart would learn from the throttling whether the number exists, and it would
            // keep hammering the numbers that answer differently
            $this->smsLoginService->logThrottledVerification($phone, $context);

            $this->addFlash(self::DANGER, $this->trans('account.loginSmsCodeInvalid'));

            return $this->redirectToLoginPage($request, $phone);
        }

        $customer = $this->smsLoginService->verifyCode($phone, $data->getString('code'), $context);

        if ($customer === null) {
            $this->addFlash(self::DANGER, $this->trans('account.loginSmsCodeInvalid'));

            return $this->redirectToLoginPage($request, $phone);
        }

        try {
            $token = $this->accountService->loginById($customer->getId(), $context);
        } catch (BadCredentialsException|CustomerNotFoundByIdException) {
            // the customer was removed or bound to another sales channel between the lookup and the
            // login, the code is gone already and the caller is told the same as for a wrong code
            $this->addFlash(self::DANGER, $this->trans('account.loginSmsCodeInvalid'));

            return $this->redirectToLoginPage($request, $phone);
        }

        $this->addCartErrors($this->cartFacade->get($token, $context));

        return $this->redirectAfterLogin($request);
    }

    private function isLoggedIn(SalesChannelContext $context): bool
    {
        $customer = $context->getCustomer();

        return $customer !== null && $customer->getGuest() === false;
    }

    /**
     * Sends the customer on after a successful login.
     *
     * The hidden field of the form is the route the customer came from, e.g. the checkout. Only a
     * storefront route is followed: an arbitrary name would end in a route exception instead of a
     * page, and the field of a post is written by whoever posts it.
     */
    private function redirectAfterLogin(Request $request): Response
    {
        if (str_starts_with($request->request->getString('redirectTo'), 'frontend.')) {
            return $this->createActionResponse($request);
        }

        return $this->redirectToRoute('frontend.account.home.page');
    }

    /**
     * Back to the form, with the number and the route the customer came from, so the next attempt
     * starts where this one did.
     */
    private function redirectToLoginPage(Request $request, string $phone): Response
    {
        $parameters = [];

        $redirectTo = $request->request->getString('redirectTo');
        if (str_starts_with($redirectTo, 'frontend.')) {
            $parameters['redirectTo'] = $redirectTo;

            $redirectParameters = $request->request->get('redirectParameters');
            if (\is_string($redirectParameters) && $redirectParameters !== '') {
                $parameters['redirectParameters'] = $redirectParameters;
            }
        }

        if ($phone !== '') {
            $parameters['phone'] = $phone;
        }

        return $this->redirectToRoute('frontend.account.login.page', $parameters);
    }
}
