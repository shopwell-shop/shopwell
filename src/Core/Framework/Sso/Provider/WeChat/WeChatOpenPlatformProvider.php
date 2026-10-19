<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Provider\WeChat;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\RequestInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\SsoException;

/**
 * WeChat Open Platform (open.weixin.qq.com) QR code login on top of the generic provider of
 * league/oauth2-client, overriding only the deviations from the OAuth2 specification that can not
 * be expressed as provider options:
 *
 *  - the client credentials are named `appid` and `secret`
 *  - the user info endpoint expects access token and openid as query parameters
 *    instead of an Authorization header
 *
 * The endpoints, the access token method, the resource owner id and the error fields are passed as
 * provider options, exactly like a static `knpu_oauth2_client` client of the `generic` type would,
 * see the channel declaration in `src/Core/Framework/DependencyInjection/sso.php`.
 */
#[Package('framework')]
class WeChatOpenPlatformProvider extends GenericProvider
{
    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        $url = parent::getResourceOwnerDetailsUrl($token);

        return $url . (\str_contains($url, '?') ? '&' : '?') . http_build_query([
            'access_token' => $token->getToken(),
            'openid' => $token->getResourceOwnerId(),
            'lang' => 'zh_CN',
        ]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function getAuthorizationParameters(array $options): array
    {
        $options = parent::getAuthorizationParameters($options);

        // WeChat names the client id `appid` and ignores the client secret on the authorize endpoint
        unset($options['client_secret'], $options['client_id']);
        $options['appid'] = $this->clientId;

        return $options;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function getAccessTokenRequest(array $params): RequestInterface
    {
        // WeChat names the credentials `appid` and `secret` on the token endpoint as well
        if (isset($params['client_id'])) {
            $params['appid'] = $params['client_id'];
            unset($params['client_id']);
        }

        if (isset($params['client_secret'])) {
            $params['secret'] = $params['client_secret'];
            unset($params['client_secret']);
        }

        return parent::getAccessTokenRequest($params);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchResourceOwnerDetails(AccessToken $token): array
    {
        // WeChat requires access token and openid as query parameters and rejects the bearer header
        $request = $this->getRequest(self::METHOD_GET, $this->getResourceOwnerDetailsUrl($token));

        $response = $this->getParsedResponse($request);

        if (!\is_array($response)) {
            throw SsoException::oauth2InvalidResourceOwnerResponse();
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new WeChatResourceOwner($response);
    }
}
