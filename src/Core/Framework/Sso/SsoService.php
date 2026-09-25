<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso;

use Shopwell\Core\Framework\Api\OAuth\AuthCodeRepository;
use Shopwell\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Config\LoginConfig;
use Shopwell\Core\Framework\Sso\Config\LoginConfigService;

/**
 * @internal
 */
#[Package('framework')]
class SsoService
{
    public function __construct(
        private readonly LoginConfigService $loginConfigService,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly AuthCodeRepository $authCodeRepository,
    ) {
    }

    public function isSso(): bool
    {
        return $this->loginConfigService->getConfig() instanceof LoginConfig;
    }

    public function revokeUserTokens(string $userId): void
    {
        $this->refreshTokenRepository->revokeRefreshTokensForUser($userId);
        $this->authCodeRepository->revokeAuthCodesForUser($userId);
    }
}
