<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\ShopSecretInvalidMiddleware;
use Shopwell\Core\Framework\Store\StoreException;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
class ShopSecretInvalidMiddlewareTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    public function testThrowsAndDeletesStoreTokensIfApiRespondsWithTokenExpiredException(): void
    {
        $this->setAllUserStoreTokens('secret_token');

        $response = new Response(401, [], '{"code":"ShopwellPlatformException-68"}');
        $request = new Psr7Request('GET', '/');

        $middleware = new ShopSecretInvalidMiddleware($this->connection, $this->systemConfigService);

        $this->expectExceptionObject(StoreException::shopSecretInvalid());
        $handler = fn (RequestInterface $req, array $options) => new FulfilledPromise($response);
        /** @var PromiseInterface $promise */
        $promise = ($middleware($handler))($request, []);

        try {
            $promise->wait();
        } finally {
            foreach ($this->fetchAllUserStoreTokens() as $token) {
                static::assertNull($token['store_token']);
            }

            static::assertNull($this->systemConfigService->get('core.store.shopSecret'));
        }
    }

    private function setAllUserStoreTokens(string $storeToken): void
    {
        $this->connection->executeStatement('UPDATE user SET store_token = :storeToken', ['storeToken' => $storeToken]);
    }

    /**
     * @return array<int, array<string|null>>
     */
    private function fetchAllUserStoreTokens(): array
    {
        return $this->connection->executeQuery('SELECT store_token FROM user')->fetchAllAssociative();
    }
}
