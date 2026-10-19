<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Controller;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The third party accounts of the signed in administrator.
 *
 * The account is always the one the access token belongs to, a channel and a user id are never
 * taken from the request: the bindings of an administrator are their own business, not a
 * privilege, so the routes only ask for the right to change the own account.
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class OAuth2IdentityController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly OAuth2LoginService $loginService,
    ) {
    }

    /**
     * The channels an administrator may log in with, each together with whether the signed in
     * administrator already linked an account of that platform.
     */
    #[Route(
        path: '/api/_action/sso/oauth2-identities',
        name: 'api.action.sso.oauth2-identities',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['user_change_me']],
        methods: [Request::METHOD_GET]
    )]
    public function list(Context $context): JsonResponse
    {
        $userId = $this->resolveUserId($context);

        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId): JsonResponse {
            $identities = $this->loginService->getUserIdentities($userId, $context);
            $channels = $this->loginService->getEnabledAdminChannels($context);

            $bindings = array_map(
                static fn (OAuth2Channel $channel): array => [
                    'technicalName' => $channel->technicalName,
                    'labelSnippet' => $channel->labelSnippet,
                    'icon' => $channel->icon,
                    'linked' => isset($identities[$channel->technicalName]),
                ],
                array_values($channels)
            );

            return new JsonResponse(['bindings' => $bindings]);
        });
    }

    /**
     * Removes the binding of the signed in administrator for one channel.
     */
    #[Route(
        path: '/api/_action/sso/oauth2-identities/{channel}',
        name: 'api.action.sso.oauth2-identities.remove',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['user_change_me']],
        methods: [Request::METHOD_DELETE]
    )]
    public function remove(string $channel, Context $context): Response
    {
        $userId = $this->resolveUserId($context);

        $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $context) => $this->loginService->unbindFromUser($channel, $userId, $context)
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function resolveUserId(Context $context): string
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw SsoException::invalidAdminSource($source::class);
        }

        $userId = $source->getUserId();

        if ($userId === null) {
            throw SsoException::userNotLoggedIn();
        }

        return $userId;
    }
}
