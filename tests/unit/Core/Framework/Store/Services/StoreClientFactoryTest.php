<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\RetryFailedStoreRequestMiddleware;
use Shopwell\Core\Framework\Store\Services\ShopSecretInvalidMiddleware;
use Shopwell\Core\Framework\Store\Services\StoreClientFactory;
use Shopwell\Core\Framework\Store\Services\StoreSessionExpiredMiddleware;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreClientFactory::class)]
class StoreClientFactoryTest extends TestCase
{
    public function testCreatesClientWithoutMiddlewares(): void
    {
        $factory = new StoreClientFactory(new StaticSystemConfigService(['core.store.apiUri' => 'http://shopwell.swag']));
        $client = $factory->create();

        static::assertInstanceOf(Client::class, $client);
        $this->assertClientConfig($client, 0);
    }

    public function testCreatesClientWithMiddlewares(): void
    {
        $connection = static::createStub(Connection::class);
        $middlewares = [
            new StoreSessionExpiredMiddleware($connection, new RequestStack()),
            new ShopSecretInvalidMiddleware($connection, new StaticSystemConfigService()),
            new RetryFailedStoreRequestMiddleware(),
        ];

        $factory = new StoreClientFactory(new StaticSystemConfigService(['core.store.apiUri' => 'http://shopwell.swag']));
        $client = $factory->create($middlewares);

        static::assertInstanceOf(Client::class, $client);
        $this->assertClientConfig($client, \count($middlewares));
    }

    private function assertClientConfig(Client $client, int $additionalMiddlewares): void
    {
        $configProperty = (new \ReflectionClass(Client::class))->getProperty('config');
        $config = $configProperty->getValue($client);

        static::assertIsArray($config);
        static::assertSame('http://shopwell.swag', (string) $config['base_uri']);
        static::assertSame('application/json', $config['headers']['Content-Type'] ?? null);
        static::assertSame('application/vnd.api+json,application/json', $config['headers']['Accept'] ?? null);
        static::assertInstanceOf(HandlerStack::class, $config['handler']);

        // HandlerStack::create() ships with 4 default middlewares (http_errors, allow_redirects, cookies, prepare_body)
        $stackProperty = (new \ReflectionClass(HandlerStack::class))->getProperty('stack');
        $stack = $stackProperty->getValue($config['handler']);
        static::assertIsArray($stack);
        static::assertCount(4 + $additionalMiddlewares, $stack);
    }
}
