<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso;

use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginCodeService;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;

/**
 * Exchanges the one time code of {@see OAuth2LoginCodeService} for an administration token.
 *
 * The authorization against the platform happened before this grant runs: the callback of the
 * admin login resolved the admin user and left a code behind. The grant therefore only verifies
 * that the code is still valid and that the user is still an active one of this shop.
 *
 * The client is not passed by the caller but pinned to the administration, a code can never be
 * exchanged for a token of another client.
 *
 * @internal
 */
#[Package('framework')]
class OAuth2LoginGrantType extends AbstractGrant
{
    public const TYPE = 'oauth2_login';

    private const CLIENT_ID = 'administration';

    /**
     * @param EntityRepository<UserCollection> $shopUserRepository
     */
    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly OAuth2LoginCodeService $codeService,
        private readonly EntityRepository $shopUserRepository,
    ) {
        $this->refreshTokenRepository = $refreshTokenRepository;
    }

    public function getIdentifier(): string
    {
        return self::TYPE;
    }

    public function respondToAccessTokenRequest(ServerRequestInterface $request, ResponseTypeInterface $responseType, \DateInterval $accessTokenTTL): ResponseTypeInterface
    {
        $client = $this->getClientEntityOrFail(self::CLIENT_ID, $request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));

        $userIdentifier = $this->validateUser($request);
        $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $userIdentifier);

        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $userIdentifier, $finalizedScopes);
        $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));
        $responseType->setAccessToken($accessToken);

        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestRefreshTokenEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request, $refreshToken));
            $responseType->setRefreshToken($refreshToken);
        }

        return $responseType;
    }

    private function validateUser(ServerRequestInterface $request): string
    {
        $code = $this->getRequestParameter('code', $request);
        if (!\is_string($code) || $code === '') {
            throw SsoException::noCodeProvided();
        }

        try {
            $userId = $this->codeService->consume($code);
            $user = $this->searchActiveUser($userId);

            if (!$user instanceof UserEntity) {
                throw SsoException::oauth2AdminUserNotFound();
            }
        } catch (\Throwable $exception) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw $exception;
        }

        return $userId;
    }

    private function searchActiveUser(string $userId): ?UserEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('id', $userId))
            ->addFilter(new EqualsFilter('active', true));

        return $this->shopUserRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }
}
