<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\TokenService;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Config\LoginConfig;
use Shopwell\Core\Framework\Sso\Config\LoginConfigService;
use Shopwell\Core\Framework\Sso\SsoException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ExternalTokenService
{
    public function __construct(
        private HttpClientInterface $client,
        private LoginConfigService $loginConfigService,
    ) {
    }

    public function getUserToken(string $code): TokenResult
    {
        $loginConfig = $this->loginConfigService->getConfig();
        if (!$loginConfig instanceof LoginConfig) {
            throw SsoException::loginConfigurationNotFound();
        }

        $tokenResponse = $this->client->request('POST', $loginConfig->baseUrl . $loginConfig->tokenPath, [
            'body' => [
                'grant_type' => 'authorization_code',
                'scope' => $loginConfig->scope,
                'client_id' => $loginConfig->clientId,
                'client_secret' => $loginConfig->clientSecret,
                'code' => $code,
                'redirect_uri' => $loginConfig->redirectUri,
            ],
        ]);

        return TokenResult::createFromResponse($tokenResponse->getContent());
    }

    public function getUserTokenByRefreshToken(string $refreshToken): TokenResult
    {
        $loginConfig = $this->loginConfigService->getConfig();
        if (!$loginConfig instanceof LoginConfig) {
            throw SsoException::loginConfigurationNotFound();
        }

        $refreshTokenResponse = $this->client->request('POST', $loginConfig->baseUrl . $loginConfig->tokenPath, [
            'body' => [
                'grant_type' => 'refresh_token',
                'scope' => $loginConfig->scope,
                'client_id' => $loginConfig->clientId,
                'client_secret' => $loginConfig->clientSecret,
                'refresh_token' => $refreshToken,
            ],
        ]);

        return TokenResult::createFromResponse($refreshTokenResponse->getContent());
    }
}
