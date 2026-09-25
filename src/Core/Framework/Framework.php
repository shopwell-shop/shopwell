<?php declare(strict_types=1);

namespace Shopwell\Core\Framework;

use Shopwell\Core\Framework\Adapter\Cache\CacheCompilerPass;
use Shopwell\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopwell\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCompilerPass;
use Shopwell\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Shopwell\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopwell\Core\Framework\Adapter\Redis\RedisConnectionsCompilerPass;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AssetBundleRegistrationCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AssetRegistrationCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\CreateGeneratorScaffoldingCommandPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\DefaultTransportCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\DemodataCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\DisableTwigCacheWarmerCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\FilesystemConfigMigrationCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\FrameworkMigrationReplacementCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\HttpCacheConfigCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\McpDebugCommandCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\McpServerBuilderCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\McpToolAnalysisCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\MessengerMiddlewareCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\OverwriteSessionFactoryCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\RateLimiterCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\RedisPrefixCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\RouteScopeCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\ScheduledTaskExecutorCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\TelemetrySubscriberCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\TwigEnvironmentCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\FrameworkExtension;
use Shopwell\Core\Framework\Feature\FeatureFlagRegistry;
use Shopwell\Core\Framework\Increment\IncrementerGatewayCompilerPass;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\MessageHandlerCompilerPass;
use Shopwell\Core\Framework\Telemetry\Metrics\MeterProvider;
use Shopwell\Core\Framework\Test\DependencyInjection\CompilerPass\ContainerVisibilityCompilerPass;
use Shopwell\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[Package('framework')]
class Framework extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

    public function getContainerExtension(): Extension
    {
        return new FrameworkExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $container->setParameter('locale', 'en-GB');

        $phpLoader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));

        $phpLoader->load('http_discovery.php');
        $phpLoader->load('acl.php');
        $phpLoader->load('api.php');
        $phpLoader->load('services.php');
        $phpLoader->load('cache.php');
        $phpLoader->load('app.php');
        $phpLoader->load('custom-field.php');
        $phpLoader->load('data-abstraction-layer.php');
        $phpLoader->load('demodata.php');
        $phpLoader->load('event.php');
        $phpLoader->load('hydrator.php');
        $phpLoader->load('filesystem.php');
        $phpLoader->load('message-queue.php');
        $phpLoader->load('plugin.php');
        $phpLoader->load('rule.php');
        $phpLoader->load('store.php');
        $phpLoader->load('scheduled-task.php');
        $phpLoader->load('script.php');
        $phpLoader->load('language.php');
        $phpLoader->load('validation.php');
        $phpLoader->load('update.php');
        $phpLoader->load('seo.php');
        $phpLoader->load('rate-limiter.php');
        $phpLoader->load('webhook.php');
        $phpLoader->load('increment.php');
        $phpLoader->load('flag.php');
        $phpLoader->load('health.php');
        $phpLoader->load('telemetry.php');
        $phpLoader->load('notification.php');
        $phpLoader->load('sso.php');

        $phpLoader->load('mcp.php');

        if ($container->getParameter('kernel.environment') === 'test') {
            $phpLoader->load('services_test.php');
            $phpLoader->load('store_test.php');
            $phpLoader->load('seo_test.php');
            $phpLoader->load('app_test.php');
        }

        /** Needs to run after @see RegisterAutoconfigureAttributesPass (priority 100) to include all services that are autoconfigured */
        $container->addCompilerPass(new AttributeEntityCompilerPass(new AttributeEntityCompiler()), PassConfig::TYPE_BEFORE_OPTIMIZATION, 99);
        // make sure to remove services behind a feature flag, before some other compiler passes may reference them, therefore the high priority
        $container->addCompilerPass(new FeatureFlagCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new DisableTwigCacheWarmerCompilerPass());
        $container->addCompilerPass(new DefaultTransportCompilerPass());
        $container->addCompilerPass(new MessengerMiddlewareCompilerPass());
        $container->addCompilerPass(new TwigLoaderConfigCompilerPass());
        $container->addCompilerPass(new TwigEnvironmentCompilerPass());
        $container->addCompilerPass(new RouteScopeCompilerPass());
        $container->addCompilerPass(new AssetRegistrationCompilerPass());
        $container->addCompilerPass(new AssetBundleRegistrationCompilerPass());
        $container->addCompilerPass(new FilesystemConfigMigrationCompilerPass());
        $container->addCompilerPass(new RateLimiterCompilerPass());
        $container->addCompilerPass(new IncrementerGatewayCompilerPass());
        $container->addCompilerPass(new ReverseProxyCompilerPass());
        $container->addCompilerPass(new CacheCompilerPass());
        $container->addCompilerPass(new OverwriteSessionFactoryCompilerPass());
        $container->addCompilerPass(new RedisPrefixCompilerPass(), PassConfig::TYPE_BEFORE_REMOVING, 0);
        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new HttpCacheConfigCompilerPass());
        $container->addCompilerPass(new MessageHandlerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new ScheduledTaskExecutorCompilerPass());
        $container->addCompilerPass(new CreateGeneratorScaffoldingCommandPass());
        $container->addCompilerPass(new RedisConnectionsCompilerPass());
        $container->addCompilerPass(new TelemetrySubscriberCompilerPass());

        if ($container->getParameter('kernel.environment') === 'test') {
            $container->addCompilerPass(new DisableRateLimiterCompilerPass());
            $container->addCompilerPass(new ContainerVisibilityCompilerPass());
        }

        $container->addCompilerPass(new FrameworkMigrationReplacementCompilerPass());
        // The discovery pass assigns plugin capabilities to an MCP server through the
        // "mcp.servers.elements" parameter, so it has to run before the bundle's own McpPass reads
        // it. That one is registered with the default priority.
        $container->addCompilerPass(new McpToolDiscoveryCompilerPass(), priority: 20);
        $container->addCompilerPass(new McpToolAnalysisCompilerPass());
        $container->addCompilerPass(new McpServerBuilderCompilerPass());
        $container->addCompilerPass(new McpDebugCommandCompilerPass());

        $container->addCompilerPass(new DemodataCompilerPass());

        parent::build($container);
        $this->buildDefaultConfig($container);
    }

    public function boot(): void
    {
        parent::boot();

        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

        $featureFlagRegistry = $this->container->get(FeatureFlagRegistry::class);
        $featureFlagRegistry->register();

        if ($this->container->getParameter('kernel.environment') !== 'test') {
            // Inject the meter early in the application lifecycle. This is needed to use the meter in special case (static contexts).
            MeterProvider::bindMeter($this->container);
        }

        CacheValueCompressor::$compress = $this->container->getParameter('shopware.cache.compress');
        CacheValueCompressor::$compressMethod = $this->container->getParameter('shopware.cache.compression_method');
        Feature::$emitDeprecations = $this->container->getParameter('kernel.debug');

        $stampedeProtectionConfigurator = $this->container->get(StampedeProtectionConfigurator::class);
        $stampedeProtectionConfigurator->apply();

        // ServicesResetter only resets initialized services; Symfony 8.1 removes this requirement.
        // https://github.com/symfony/symfony/pull/63751
        // The test verifies whether the Symfony fix resolves this and lets us remove this workaround safely.
        // @see \Shopwell\Tests\Integration\Core\Framework\Adapter\Database\ReplicaConnectionResetterTest::testServicesResetterInitializesReplicaConnectionResetter()
        $this->container->get(ReplicaConnectionResetter::class);
    }
}
