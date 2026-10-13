<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use KnpU\OAuth2ClientBundle\Exception\InvalidStateException;
use KnpU\OAuth2ClientBundle\Exception\MissingAuthorizationCodeException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\Framework\Sso\Struct\OAuth2Identity;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The storefront side of the OAuth2 login.
 *
 * Both steps of the round trip run through this controller, so the redirect the platform is asked
 * for and the one the token is exchanged with are the same, see {@see \KnpU\OAuth2ClientBundle\Client\OAuth2Client}.
 * Everything that touches the shop accounts happens in the service, the controller only translates
 * between HTTP and the login.
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class OAuth2LoginController extends StorefrontController
{
    /**
     * The route the platform redirects back to.
     */
    private const CALLBACK_ROUTE = 'frontend.oauth2-login.callback';

    /**
     * Whether the running round trip logs a customer in or binds the external account to the signed
     * in customer.
     */
    private const INTENT_SESSION_KEY = 'oauth2-login.intent';

    private const INTENT_LOGIN = 'login';
    private const INTENT_BIND = 'bind';

    private const REDIRECT_SESSION_KEY = 'oauth2-login.redirect';

    /**
     * @internal
     */
    public function __construct(
        private readonly OAuth2LoginService $loginService,
        private readonly AccountService $accountService,
    ) {
    }

    #[Route(
        path: '/oauth2-login/{channel}',
        name: 'frontend.oauth2-login.start',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function start(Request $request, SalesChannelContext $context, string $channel): Response
    {
        try {
            $redirect = $this->loginService->createAuthorizationRedirect($channel, self::CALLBACK_ROUTE, $context->getContext());
        } catch (SsoException) {
            $this->addFlash(self::DANGER, $this->trans('sso.oauth2.error.notAvailable'));

            return $this->redirectToRoute('frontend.account.login.page');
        }

        $session = $request->getSession();
        $session->set(
            self::INTENT_SESSION_KEY,
            $request->query->getBoolean('bind') && $context->getCustomer() !== null ? self::INTENT_BIND : self::INTENT_LOGIN
        );
        $session->set(self::REDIRECT_SESSION_KEY, $this->resolveRedirectTarget($request));

        return $redirect;
    }

    #[Route(
        path: '/oauth2-login/{channel}/callback',
        name: 'frontend.oauth2-login.callback',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function callback(Request $request, SalesChannelContext $context, string $channel): Response
    {
        $session = $request->getSession();
        $intent = $session->get(self::INTENT_SESSION_KEY, self::INTENT_LOGIN);

        try {
            $identity = $this->loginService->fetchIdentity($channel, self::CALLBACK_ROUTE, $context->getContext());

            if ($intent === self::INTENT_BIND) {
                $this->bind($identity, $context);
            } else {
                $customerId = $this->loginService->resolveCustomer($identity, $context);
                $this->accountService->loginById($customerId, $context);
                $this->addFlash(self::SUCCESS, $this->trans('sso.oauth2.loginSucceeded'));
            }
        } catch (SsoException $exception) {
            $this->addFlash(self::DANGER, $this->transError($exception));
        } catch (InvalidStateException|MissingAuthorizationCodeException|IdentityProviderException) {
            $this->addFlash(self::DANGER, $this->trans('sso.oauth2.error.failed'));
        } finally {
            $session->remove(self::INTENT_SESSION_KEY);
        }

        $target = $session->remove(self::REDIRECT_SESSION_KEY);

        return $target === null
            ? $this->redirectToRoute('frontend.account.home.page')
            : $this->redirectToRoute($target);
    }

    /**
     * Unlinks the external account of the channel from the signed in customer.
     */
    #[Route(
        path: '/account/oauth2-login/{channel}',
        name: 'frontend.account.oauth2-login.unbind',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function unbind(RequestDataBag $data, SalesChannelContext $context, string $channel): Response
    {
        $customer = $context->getCustomer();

        if ($customer === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->loginService->unbindFromCustomer($channel, $customer->getId(), $context->getContext());
            $this->addFlash(self::SUCCESS, $this->trans('sso.oauth2.account.unbindSucceeded'));
        } catch (SsoException) {
            $this->addFlash(self::DANGER, $this->trans('sso.oauth2.account.unbindFailed'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    private function bind(OAuth2Identity $identity, SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();

        if ($customer === null) {
            throw SsoException::oauth2IdentityNotBound($identity->getTechnicalName());
        }

        $this->loginService->bindToCustomer($identity, $customer->getId(), $context->getContext());
        $this->addFlash(self::SUCCESS, $this->trans('sso.oauth2.account.bindSucceeded'));
    }

    /**
     * Only a route of the storefront is accepted, so a crafted `redirectTo` can not turn the login
     * into an open redirect.
     */
    private function resolveRedirectTarget(Request $request): ?string
    {
        $target = $request->query->getString('redirectTo');

        return \str_starts_with($target, 'frontend.') ? $target : null;
    }

    private function transError(SsoException $exception): string
    {
        return match ($exception->getErrorCode()) {
            SsoException::OAUTH2_MISSING_VERIFIED_EMAIL,
            SsoException::OAUTH2_IDENTITY_NOT_BOUND,
            SsoException::OAUTH2_IDENTITY_ALREADY_BOUND => 'sso.oauth2.error.notLinked',
            default => 'sso.oauth2.error.failed',
        };
    }
}
