<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\DependencyInjection\ServiceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceExtension::class)]
class ServiceExtensionTest extends TestCase
{
    #[DataProvider('environmentProvider')]
    public function testRegistryUrlIsResolvedFromTheEnvironmentVariable(string $environment): void
    {
        $container = $this->prepend($environment);

        static::assertSame('%env(service-registry-url:SERVICE_REGISTRY_URL)%', $container->getParameter('shopwell.service_registry.url'));
    }

    public function testRegistryUrlIsRestrictedToShopwellDomainsInProduction(): void
    {
        $container = $this->prepend('prod');

        static::assertSame(['shopwell.cn'], $container->getParameter('shopwell.service_registry.trusted_domains'));
    }

    #[DataProvider('nonProductionEnvironmentProvider')]
    public function testRegistryUrlIsUnrestrictedOutsideOfProduction(string $environment): void
    {
        $container = $this->prepend($environment);

        static::assertSame([], $container->getParameter('shopwell.service_registry.trusted_domains'));
    }

    public function testRegistryHttpClientUsesTheResolvedRegistryUrl(): void
    {
        $container = $this->prepend('prod');

        static::assertSame([[
            'http_client' => [
                'scoped_clients' => [
                    'service_registry.http_client' => [
                        'base_uri' => '%shopwell.service_registry.url%',
                        'max_duration' => 5,
                    ],
                ],
            ],
        ]], $container->getExtensionConfig('framework'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function environmentProvider(): iterable
    {
        yield 'production environment' => ['prod'];

        yield from self::nonProductionEnvironmentProvider();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonProductionEnvironmentProvider(): iterable
    {
        yield 'development environment' => ['dev'];
        yield 'test environment' => ['test'];
    }

    private function prepend(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.environment' => $environment]));

        (new ServiceExtension())->prepend($container);

        return $container;
    }
}
