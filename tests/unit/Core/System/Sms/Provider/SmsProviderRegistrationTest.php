<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Integration\Null\NullSmsTransport;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\Audit\SmsProviderCapabilities;
use Shopwell\Core\System\Sms\Provider\SmsProviderRegistry;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsProviderRegistry::class)]
class SmsProviderRegistrationTest extends TestCase
{
    public function testAPluginServiceIsDiscoveredThroughTheProductionProviderTag(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator()))->load(\dirname(__DIR__, 6) . '/src/Core/System/DependencyInjection/sms.php');

        // Keep the production discovery definition; other SMS services need a full kernel.
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($id !== SmsProviderRegistry::class) {
                $container->removeDefinition($id);
            }
        }

        foreach (array_keys($container->getAliases()) as $id) {
            $container->removeAlias($id);
        }
        $container->register('service_container', ContainerInterface::class)->setSynthetic(true);
        $container->register(PluginSmsProvider::class)->addTag('shopwell.sms.provider');
        $container->getDefinition(SmsProviderRegistry::class)->setPublic(true);
        $container->compile();

        $providers = $container->get(SmsProviderRegistry::class);
        static::assertInstanceOf(SmsProviderRegistry::class, $providers);
        static::assertSame('plugin', $providers->get('plugin')->getTechnicalName());
        static::assertTrue($providers->get('plugin')->supportsTemplateMode());
        static::assertFalse((new SmsProviderCapabilities($providers))->requiresApproval('plugin'));
        static::assertSame('null', (string) $providers->get('plugin')->createTransport([], []));
    }
}

/**
 * @internal
 */
#[Package('framework')]
class PluginSmsProvider extends AbstractSmsProvider
{
    public function getTechnicalName(): string
    {
        return 'plugin';
    }

    public function getLabelSnippet(): string
    {
        return 'plugin.sms.label';
    }

    public function supportsTemplateMode(): bool
    {
        return true;
    }

    public function createTransport(array $credentials, array $options, ?EventDispatcherInterface $dispatcher = null): TransportInterface
    {
        return new NullSmsTransport($dispatcher);
    }
}
