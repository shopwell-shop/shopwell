<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Sso;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Nyholm\Psr7\Response as Psr7Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\OAuth\AccessTokenRepository;
use Shopwell\Core\Framework\Api\OAuth\ClientRepository;
use Shopwell\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopwell\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopwell\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopwell\Core\Framework\Api\OAuth\ScopeRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Config\LoginConfigService;
use Shopwell\Core\Framework\Sso\ShopwellGrantType;
use Shopwell\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopwell\Core\Framework\Sso\UserService\UserService;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\Checkout\Payment\Cart\Token\TestKey;
use Shopwell\Core\Test\Stub\Checkout\Payment\Cart\Token\TestSigner;
use Shopwell\Tests\Integration\Core\Framework\Sso\Helper\FakeTokenGenerator;
use Shopwell\Tests\Integration\Core\Framework\Sso\Helper\FakeUserInstaller;
use Shopwell\Tests\Integration\Core\Framework\Sso\Helper\ValidUserServiceCreator;
use Shopwell\Tests\Unit\Core\Framework\Sso\TokenService\_fixtures\JwksIds;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
class ShopwellGrantTypeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->userService = (new ValidUserServiceCreator('create'))->create(
            $this->connection,
            static::getContainer()->get('user.repository'),
        );
    }

    /**
     * @return \Generator<string, array{?\DateInterval, bool, bool}>
     */
    public static function authenticationTimeProvider(): \Generator
    {
        yield 'identity provider login just happened' => [new \DateInterval('PT10S'), true, false];
        yield 'identity provider session was reused' => [new \DateInterval('PT1H'), false, false];
        yield 'identity provider does not report auth_time' => [null, false, false];
        yield 'returning user, identity provider login just happened' => [new \DateInterval('PT10S'), true, true];
        yield 'returning user, identity provider session was reused' => [new \DateInterval('PT1H'), false, true];
    }

    /**
     * @param \DateInterval|null $authenticatedAgo how long before the code exchange the identity provider authenticated the user
     * @param bool $returningUser whether the user already logged in through the identity provider before
     */
    #[DataProvider('authenticationTimeProvider')]
    public function testRespondToAccessTokenRequest(?\DateInterval $authenticatedAgo, bool $verified, bool $returningUser): void
    {
        $email = 'test@shopwell.cn';

        $fakeUserInstall = new FakeUserInstaller($this->connection);
        $fakeUserInstall->installBaseUserData(Uuid::randomHex(), $email);

        if ($returningUser) {
            $this->requestAccessToken((new FakeTokenGenerator())->setEmail($email)->generate(JwksIds::KEY_ID_ONE));
        }

        $tokenGenerator = (new FakeTokenGenerator())->setEmail($email);
        if ($authenticatedAgo !== null) {
            $tokenGenerator->setAuthTime((new \DateTimeImmutable())->sub($authenticatedAgo));
        }

        $responseBodyData = $this->requestAccessToken($tokenGenerator->generate(JwksIds::KEY_ID_ONE));

        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame('Bearer', $responseBodyData['token_type']);
        // Assert that expires_in is between 3595 and 3600 to account for minor delays in processing
        static::assertTrue($responseBodyData['expires_in'] <= 3600 && $responseBodyData['expires_in'] > 3595);
        static::assertIsString($responseBodyData['access_token']);
        static::assertIsString($responseBodyData['refresh_token']);

        $jwt = $responseBodyData['access_token'];
        static::assertNotSame('', $jwt);
        $accessToken = (new Parser(new JoseEncoder()))->parse($jwt);
        static::assertInstanceOf(UnencryptedToken::class, $accessToken);
        static::assertSame($verified, \in_array(UserVerifiedScope::IDENTIFIER, $accessToken->claims()->get('scopes', []), true));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestAccessToken(string $idToken): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('sso_proof_key_verifier', 'proofKeyVerifier');

        $shopwellGrantType = new ShopwellGrantType(
            new RefreshTokenRepository($this->connection, new NativeClock()),
            $this->userService,
            $this->createExternalTokenService($idToken),
            new NativeClock()
        );

        $shopwellGrantType->setClientRepository($this->getContainer()->get(ClientRepository::class));
        $shopwellGrantType->setScopeRepository($this->getContainer()->get(ScopeRepository::class));
        $shopwellGrantType->setAccessTokenRepository($this->getContainer()->get(AccessTokenRepository::class));
        $shopwellGrantType->setPrivateKey(new FakeCryptKey(Configuration::forSymmetricSigner(new TestSigner(), new TestKey())));
        $shopwellGrantType->setRefreshTokenTTL(new \DateInterval('PT1H'));
        $shopwellGrantType->setDefaultScope('');

        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->server->set('HTTPS', 'on');
        $request->request->set('code', 'code');

        $psr7Request = $this->getContainer()->get(PsrHttpFactory::class)->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $responseResult = $shopwellGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, new \DateInterval('PT1H'));
        static::assertInstanceOf(BearerTokenResponse::class, $responseResult);
        $result = $responseResult->generateHttpResponse(new Psr7Response());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);

        return $responseBodyData;
    }

    private function createExternalTokenService(string $token): ExternalTokenService
    {
        $responseInterface = static::createStub(ResponseInterface::class);
        $responseInterface->method('getContent')->willReturn(
            \json_encode(
                [
                    'id_token' => $token,
                    'access_token' => 'access_token',
                    'refresh_token' => 'refresh_token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                    'scope' => 'scope',
                ]
            )
        );

        $client = static::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($responseInterface);

        $loginConfig = new LoginConfigService(
            [
                'use_default' => false,
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_uri' => 'http://redirect.uri',
                'base_url' => 'http://base.uri',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            static::createStub(RouterInterface::class)
        );

        return new ExternalTokenService($client, $loginConfig);
    }
}
