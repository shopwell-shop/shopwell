<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Controller;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\Service\OAuth2ChannelFormSchema;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tells the administration which login channels this shop can be configured with, and which fields
 * each of them has.
 *
 * A channel is code, only its credentials are data. The administration therefore asks here instead
 * of shipping a list of channels and a form of its own, which would silently drift apart from the
 * ones the shop actually supports - including the ones an extension adds.
 *
 * Only the shape of the form is answered here, never a value: the credentials of a channel are data
 * of the shop and are read from their repository like any other row.
 *
 * @internal
 */
#[Package('framework')]
class OAuth2ChannelController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly OAuth2ChannelRegistry $channelRegistry,
        private readonly OAuth2ChannelFormSchema $formSchema,
    ) {
    }

    #[Route(
        path: '/api/_action/sso/oauth2-channels',
        name: 'api.action.sso.oauth2-channels',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['oauth2_client:read']],
        methods: [Request::METHOD_GET]
    )]
    public function list(): JsonResponse
    {
        $channels = array_map(
            fn (OAuth2Channel $channel): array => [
                'technicalName' => $channel->technicalName,
                'labelSnippet' => $channel->labelSnippet,
                'icon' => $channel->icon,
                'defaultScopes' => $channel->defaultScopes,
                'providesVerifiedEmail' => $channel->providesVerifiedEmail,
                'supportsAdminLogin' => $channel->supportsAdminLogin,
                'formFields' => $this->formSchema->build($channel),
            ],
            $this->channelRegistry->all()
        );

        return new JsonResponse(['channels' => array_values($channels)]);
    }
}
