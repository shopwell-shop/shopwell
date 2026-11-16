<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopwell\Storefront\Theme\ThemeService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Guards the service definitions of the Storefront theme.php.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversNothing]
class ThemeServiceConfigTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $loader = new PhpFileLoader($this->container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../src/Storefront/DependencyInjection/theme.php');
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function resettableServices(): iterable
    {
        yield 'sales channel theme loader memoises the themes per sales channel' => [DatabaseSalesChannelThemeLoader::class];
        yield 'theme service notifies the start of an async compilation only once' => [ThemeService::class];
    }

    /**
     * @param class-string $serviceId
     */
    #[DataProvider('resettableServices')]
    public function testServiceIsResetBetweenRequestsAndWorkerMessages(string $serviceId): void
    {
        static::assertSame(
            [['method' => 'reset']],
            $this->container->getDefinition($serviceId)->getTag('kernel.reset'),
        );
    }
}
