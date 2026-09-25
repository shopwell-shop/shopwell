<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Redis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopwell\Core\Framework\Adapter\AdapterException;
use Shopwell\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ServiceLocatorTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RedisConnectionProvider::class)]
class RedisConnectionProviderTest extends TestCase
{
    /**
     * @var \stdClass[]
     */
    private array $connections;

    private ContainerInterface $serviceLocator;

    private RedisConnectionProvider $redisConnectionProvider;

    protected function setUp(): void
    {
        $this->connections = [
            'persistent' => new \stdClass(),
            'ephemeral' => new \stdClass(),
        ];

        $factories = [
            'shopwell.redis.connection.persistent' => fn () => $this->connections['persistent'],
            'shopwell.redis.connection.ephemeral' => fn () => $this->connections['ephemeral'],
        ];

        $this->serviceLocator = new class($factories) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        $this->redisConnectionProvider = new RedisConnectionProvider($this->serviceLocator);
    }

    public function testGetConnection(): void
    {
        $connection = $this->redisConnectionProvider->getConnection('persistent');
        static::assertSame($this->connections['persistent'], $connection);

        $connection = $this->redisConnectionProvider->getConnection('ephemeral');
        static::assertSame($this->connections['ephemeral'], $connection);

        $this->expectException(AdapterException::class);
        $this->redisConnectionProvider->getConnection('some-non-existing-connection');
    }

    public function testHasConnection(): void
    {
        static::assertTrue($this->redisConnectionProvider->hasConnection('persistent'));
        static::assertTrue($this->redisConnectionProvider->hasConnection('ephemeral'));
        static::assertFalse($this->redisConnectionProvider->hasConnection('some-non-existing-connection'));
    }
}
