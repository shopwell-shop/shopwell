<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\ShopwellGrantType;
use Shopwell\Core\Framework\Sso\ShopwellPasswordGrantType;
use Shopwell\Core\Framework\Sso\ShopwellRefreshTokenGrantType;
use Shopwell\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopwell\Core\Framework\Sso\UserService\UserService;

/**
 * Builds the grant types that are enabled on the Admin API authorization server for each request.
 *
 * @internal
 */
#[Package('framework')]
final class GrantTypeFactory
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly AuthCodeRepositoryInterface $authCodeRepository,
        private readonly UserService $userService,
        private readonly ExternalTokenService $tokenService,
        private readonly ClockInterface $clock,
        private readonly string $refreshTokenTtl = 'P1W',
        private readonly string $authCodeTtl = 'PT5M',
    ) {
    }

    /**
     * @return list<GrantTypeInterface>
     */
    public function createGrantTypes(): array
    {
        $refreshTokenInterval = new \DateInterval($this->refreshTokenTtl);

        $passwordGrant = new ShopwellPasswordGrantType($this->userRepository, $this->refreshTokenRepository, $this->userService);
        $passwordGrant->setRefreshTokenTTL($refreshTokenInterval);

        $refreshTokenGrant = new ShopwellRefreshTokenGrantType(
            $this->refreshTokenRepository,
            $this->userService,
            $this->tokenService,
            $this->clock
        );
        $refreshTokenGrant->setRefreshTokenTTL($refreshTokenInterval);

        $shopwareGrant = new ShopwellGrantType($this->refreshTokenRepository, $this->userService, $this->tokenService, $this->clock);
        $shopwareGrant->setRefreshTokenTTL($refreshTokenInterval);

        $authCodeGrant = new ShopwellAuthCodeGrantType(
            $this->authCodeRepository,
            $this->refreshTokenRepository,
            new \DateInterval($this->authCodeTtl)
        );
        $authCodeGrant->setRefreshTokenTTL($refreshTokenInterval);

        return [
            $passwordGrant,
            $refreshTokenGrant,
            new ClientCredentialsGrant(),
            $shopwareGrant,
            $authCodeGrant,
        ];
    }
}
