<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\OAuth\GrantTypeFactory;
use Shopwell\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopwell\Core\Framework\Api\OAuth\ShopwellAuthCodeGrantType;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Config\LoginConfigService;
use Shopwell\Core\Framework\Sso\ShopwellGrantType;
use Shopwell\Core\Framework\Sso\ShopwellPasswordGrantType;
use Shopwell\Core\Framework\Sso\ShopwellRefreshTokenGrantType;
use Shopwell\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopwell\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopwell\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopwell\Core\Framework\Sso\UserService\UserService;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GrantTypeFactory::class)]
class GrantTypeFactoryTest extends TestCase
{
    public function testCreatesAllGrantTypesOfTheAdminApi(): void
    {
        $clock = new NativeClock();
        $loginConfigService = static::createStub(LoginConfigService::class);
        $externalTokenService = new ExternalTokenService(static::createStub(HttpClientInterface::class), $loginConfigService);
        $userService = new UserService(
            static::createStub(Connection::class),
            new IdTokenParser(
                new PublicKeyLoader(static::createStub(HttpClientInterface::class), $loginConfigService, new ArrayAdapter()),
                $loginConfigService,
                $clock
            ),
            StaticEntityRepository::of(UserCollection::class, []),
            $externalTokenService,
            $clock,
        );

        $factory = new GrantTypeFactory(
            static::createStub(UserRepositoryInterface::class),
            static::createStub(RefreshTokenRepository::class),
            static::createStub(AuthCodeRepositoryInterface::class),
            $userService,
            $externalTokenService,
            $clock,
        );

        $grantTypes = $factory->createGrantTypes();

        $identifiers = array_map(static fn ($grantType) => $grantType->getIdentifier(), $grantTypes);
        static::assertSame(
            ['password', 'refresh_token', 'client_credentials', ShopwellGrantType::TYPE, 'authorization_code'],
            $identifiers
        );

        static::assertInstanceOf(ShopwellPasswordGrantType::class, $grantTypes[0]);
        static::assertInstanceOf(ShopwellRefreshTokenGrantType::class, $grantTypes[1]);
        static::assertInstanceOf(ShopwellGrantType::class, $grantTypes[3]);
        static::assertInstanceOf(ShopwellAuthCodeGrantType::class, $grantTypes[4]);
    }
}
