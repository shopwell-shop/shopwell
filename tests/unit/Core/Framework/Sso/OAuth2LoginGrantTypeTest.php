<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso;

use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\OAuth2LoginGrantType;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginCodeService;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2LoginGrantType::class)]
class OAuth2LoginGrantTypeTest extends TestCase
{
    public function testIdentifier(): void
    {
        static::assertSame('oauth2_login', $this->createGrant()->getIdentifier());
    }

    public function testThrowsWithoutACode(): void
    {
        $this->expectExceptionObject(SsoException::noCodeProvided());

        $this->createGrant()->respondToAccessTokenRequest(
            $this->createRequest(),
            static::createStub(ResponseTypeInterface::class),
            new \DateInterval('PT10M')
        );
    }

    public function testThrowsForACodeThatDoesNotExist(): void
    {
        $this->expectExceptionObject(SsoException::oauth2InvalidLoginCode());

        $this->createGrant()->respondToAccessTokenRequest(
            $this->createRequest(['code' => 'not-a-code-of-this-shop']),
            static::createStub(ResponseTypeInterface::class),
            new \DateInterval('PT10M')
        );
    }

    public function testThrowsWhenTheUserOfTheCodeIsNotActive(): void
    {
        $clock = new MockClock();
        $codeService = new OAuth2LoginCodeService(new ArrayAdapter(), $clock);

        $this->expectExceptionObject(SsoException::oauth2AdminUserNotFound());

        $this->createGrant($codeService)->respondToAccessTokenRequest(
            $this->createRequest(['code' => $codeService->create('user-id')]),
            static::createStub(ResponseTypeInterface::class),
            new \DateInterval('PT10M')
        );
    }

    public function testIssuesATokenForTheUserOfTheCode(): void
    {
        $clock = new MockClock();
        $codeService = new OAuth2LoginCodeService(new ArrayAdapter(), $clock);

        $user = new UserEntity();
        $user->setId('user-id');
        $user->setUniqueIdentifier('user-id');
        $user->setActive(true);

        $grant = $this->createGrant($codeService, [$user]);

        $responseType = $this->createMock(ResponseTypeInterface::class);
        $responseType->expects($this->once())->method('setAccessToken');

        $result = $grant->respondToAccessTokenRequest(
            $this->createRequest(['code' => $codeService->create('user-id')]),
            $responseType,
            new \DateInterval('PT10M')
        );

        static::assertSame($responseType, $result);
    }

    /**
     * @param list<UserEntity> $users
     */
    private function createGrant(
        ?OAuth2LoginCodeService $codeService = null,
        array $users = [],
    ): OAuth2LoginGrantType {
        $clock = new MockClock();

        $clientRepository = static::createStub(ClientRepositoryInterface::class);
        $clientRepository->method('getClientEntity')->willReturn(static::createStub(ClientEntityInterface::class));

        $scopeRepository = static::createStub(ScopeRepositoryInterface::class);
        $scopeRepository->method('finalizeScopes')->willReturn([]);

        $accessTokenRepository = static::createStub(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->method('getNewToken')->willReturn(static::createStub(AccessTokenEntityInterface::class));

        $refreshTokenRepository = static::createStub(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->method('getNewRefreshToken')->willReturn(static::createStub(RefreshTokenEntityInterface::class));

        $grant = new OAuth2LoginGrantType(
            $refreshTokenRepository,
            $codeService ?? new OAuth2LoginCodeService(new ArrayAdapter(), $clock),
            StaticEntityRepository::of(UserCollection::class, [new UserCollection($users)]),
        );

        $grant->setClientRepository($clientRepository);
        $grant->setScopeRepository($scopeRepository);
        $grant->setAccessTokenRepository($accessTokenRepository);
        $grant->setPrivateKey(static::createStub(CryptKeyInterface::class));
        $grant->setDefaultScope('');
        // `GrantTypeFactory` hands the TTL to the grant in production; a refresh token is issued
        // as soon as the access token one is, so the fixture has to provide it as well.
        $grant->setRefreshTokenTTL(new \DateInterval('P1W'));

        return $grant;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function createRequest(array $parameters = []): ServerRequest
    {
        return (new ServerRequest('POST', '/api/oauth/token'))->withParsedBody(
            $parameters + ['grant_type' => OAuth2LoginGrantType::TYPE, 'client_id' => 'administration']
        );
    }
}
