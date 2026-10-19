<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Controller;

use KnpU\OAuth2ClientBundle\Exception\InvalidStateException;
use KnpU\OAuth2ClientBundle\Exception\MissingAuthorizationCodeException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Server\AuthorizationServer;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\LoginResponseService;
use Shopwell\Core\Framework\Sso\OAuth2LoginGrantType;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginCodeService;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The administration side of the third party login.
 *
 * Both steps of the round trip run through this controller and end in the same redirect route, see
 * {@see \KnpU\OAuth2ClientBundle\Client\OAuth2Client}. The callback does not hand a token to the
 * browser itself: it leaves a one time code behind and exchanges that code through the
 * authorization server, exactly like the browser would. The result is the same `bearerAuth` cookie
 * the password login produces, so the administration does not need to know how it got logged in.
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class OAuth2AdminLoginController extends AbstractController
{
    /**
     * The route the platform redirects back to.
     */
    private const CALLBACK_ROUTE = 'api.oauth2-login.admin.callback';

    private const ADMIN_CLIENT_ID = 'administration';

    /**
     * @internal
     */
    public function __construct(
        private readonly OAuth2LoginService $loginService,
        private readonly OAuth2LoginCodeService $codeService,
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly LoginResponseService $loginResponseService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The channels the login page of the administration offers, together with the url the browser
     * has to open to start the round trip.
     */
    #[Route(
        path: '/api/_info/oauth2-login-channels',
        name: 'api.info.oauth2-login-channels',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function channels(): JsonResponse
    {
        $channels = array_map(
            fn (OAuth2Channel $channel): array => [
                'technicalName' => $channel->technicalName,
                'labelSnippet' => $channel->labelSnippet,
                'icon' => $channel->icon,
                'startUrl' => $this->urlGenerator->generate('api.oauth2-login.admin.start', ['channel' => $channel->technicalName]),
            ],
            $this->loginService->getEnabledAdminChannels(Context::createDefaultContext())
        );

        return new JsonResponse(['channels' => array_values($channels)]);
    }

    #[Route(
        path: '/api/oauth2-login/admin/{channel}',
        name: 'api.oauth2-login.admin.start',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function start(string $channel): Response
    {
        try {
            return $this->loginService->createAuthorizationRedirect($channel, self::CALLBACK_ROUTE, Context::createDefaultContext());
        } catch (SsoException $exception) {
            return $this->loginResponseService->createOAuth2ErrorResponse($exception->getErrorCode());
        }
    }

    #[Route(
        path: '/api/oauth2-login/admin/{channel}/callback',
        name: 'api.oauth2-login.admin.callback',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function callback(string $channel): Response
    {
        try {
            $identity = $this->loginService->fetchIdentity($channel, self::CALLBACK_ROUTE, Context::createDefaultContext());
            $userId = $this->loginService->resolveAdminUser($identity, Context::createDefaultContext());
        } catch (SsoException $exception) {
            return $this->loginResponseService->createOAuth2ErrorResponse($exception->getErrorCode());
        } catch (InvalidStateException|MissingAuthorizationCodeException|IdentityProviderException) {
            return $this->loginResponseService->createOAuth2ErrorResponse(SsoException::OAUTH2_AUTHORIZATION_FAILED);
        }

        $response = $this->authorizationServer->respondToAccessTokenRequest(
            $this->psrHttpFactory->createRequest($this->createTokenRequest($userId)),
            $this->psrHttpFactory->createResponse(new Response())
        );

        return $this->loginResponseService->create($response);
    }

    /**
     * The token request the browser would send, built server side from the one time code.
     */
    private function createTokenRequest(string $userId): Request
    {
        return Request::create(
            '/api/oauth/token',
            Request::METHOD_POST,
            [
                'grant_type' => OAuth2LoginGrantType::TYPE,
                'client_id' => self::ADMIN_CLIENT_ID,
                'scope' => 'write',
                'code' => $this->codeService->create($userId),
            ]
        );
    }
}
