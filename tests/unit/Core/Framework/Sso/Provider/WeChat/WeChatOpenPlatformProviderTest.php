<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Provider\WeChat;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Provider\WeChat\WeChatOpenPlatformProvider;
use Shopwell\Core\Framework\Sso\Provider\WeChat\WeChatResourceOwner;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WeChatOpenPlatformProvider::class)]
class WeChatOpenPlatformProviderTest extends TestCase
{
    public function testSendsTheClientIdAsAppIdOnTheAuthorizeEndpoint(): void
    {
        $url = $this->provider()->getAuthorizationUrl(['scope' => ['snsapi_login']]);

        $query = $this->query($url);

        static::assertSame('appid', $query['appid']);
        static::assertArrayNotHasKey('client_id', $query);
        static::assertSame('https://shop.test/oauth2/callback', $query['redirect_uri']);
    }

    public function testUsesTheClientSecretOnTheTokenEndpoint(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('send')
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"token-value","expires_in":7200,"openid":"open-id","scope":"snsapi_login"}'));

        $token = $this->provider($httpClient)->getAccessToken('authorization_code', ['code' => 'the-code']);

        static::assertInstanceOf(AccessToken::class, $token);
        static::assertSame('token-value', $token->getToken());
        static::assertSame('open-id', $token->getResourceOwnerId());
    }

    public function testRequestsTheAccessTokenAsQueryApi(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('send')
            ->with(static::callback(static function (object $request): bool {
                \assert($request instanceof RequestInterface);

                $query = $request->getUri()->getQuery();

                return str_contains($query, 'appid=appid')
                    && str_contains($query, 'secret=secret')
                    && str_contains($query, 'grant_type=authorization_code');
            }))
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"token-value","expires_in":7200,"openid":"open-id"}'));

        $this->provider($httpClient)->getAccessToken('authorization_code', ['code' => 'the-code']);
    }

    public function testCarriesTokenAndOpenIdOnTheUserInfoEndpoint(): void
    {
        $token = new AccessToken(['access_token' => 'token-value', 'resource_owner_id' => 'open-id']);

        $query = $this->query($this->provider()->getResourceOwnerDetailsUrl($token));

        static::assertSame('token-value', $query['access_token']);
        static::assertSame('open-id', $query['openid']);
        static::assertSame('zh_CN', $query['lang']);
    }

    public function testReadsTheUserInfoAsResourceOwner(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('send')
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], '{"unionid":"union-id","openid":"open-id","nickname":"Jane","headimgurl":"https://example.com/head.png"}'));

        $owner = $this->provider($httpClient)->getResourceOwner(
            new AccessToken(['access_token' => 'token-value', 'resource_owner_id' => 'open-id'])
        );

        static::assertInstanceOf(WeChatResourceOwner::class, $owner);
        static::assertSame('union-id', $owner->getId());
    }

    public function testReportsTheErrorOfWeChatEvenThoughItAnswersWithStatus200(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('send')
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], '{"errcode":40029,"errmsg":"invalid code"}'));

        $this->expectExceptionObject(new IdentityProviderException('invalid code', 40029, []));

        $this->provider($httpClient)->getAccessToken('authorization_code', ['code' => 'the-code']);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function query(string $url): array
    {
        $query = [];
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

        return $query;
    }

    private function provider(?ClientInterface $httpClient = null): WeChatOpenPlatformProvider
    {
        $options = [
            'clientId' => 'appid',
            'clientSecret' => 'secret',
            'redirectUri' => 'https://shop.test/oauth2/callback',
            'urlAuthorize' => 'https://open.weixin.qq.com/connect/qrconnect',
            'urlAccessToken' => 'https://api.weixin.qq.com/sns/oauth2/access_token',
            'urlResourceOwnerDetails' => 'https://api.weixin.qq.com/sns/userinfo',
            'accessTokenMethod' => 'GET',
            'accessTokenResourceOwnerId' => 'openid',
            'responseError' => 'errmsg',
            'responseCode' => 'errcode',
        ];

        return new WeChatOpenPlatformProvider($options, $httpClient === null ? [] : ['httpClient' => $httpClient]);
    }
}
