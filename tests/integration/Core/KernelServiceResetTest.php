<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Test\TestResetCounter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers the request lifecycle contract of `Shopwell\Core\Kernel` that long running
 * runtimes (FrankenPHP, RoadRunner, Swoole) rely on: with `LONGRUNNING_ENVIRONMENT` Symfony resets
 * all `kernel.reset` services between two requests handled by the same kernel instance.
 *
 * @internal
 */
#[Package('framework')]
class KernelServiceResetTest extends TestCase
{
    public function testServicesAreResetBetweenTwoRequests(): void
    {
        Feature::skipTestIfInActive('LONGRUNNING_ENVIRONMENT', $this);

        $kernel = KernelLifecycleManager::createKernel();
        $kernel->boot();

        try {
            $counter = $kernel->getContainer()->get(TestResetCounter::class);
            static::assertInstanceOf(TestResetCounter::class, $counter);

            $kernel->handle(Request::create('/api/_info/version'));

            // the reset happens lazily at the start of the next request, not after the previous one
            static::assertSame(0, $counter->resetCount);

            $kernel->handle(Request::create('/api/_info/version'));

            static::assertSame(1, $counter->resetCount);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testServicesAreNotResetWithoutLongrunningEnvironment(): void
    {
        Feature::skipTestIfActive('LONGRUNNING_ENVIRONMENT', $this);

        $kernel = KernelLifecycleManager::createKernel();
        $kernel->boot();

        try {
            $counter = $kernel->getContainer()->get(TestResetCounter::class);
            static::assertInstanceOf(TestResetCounter::class, $counter);

            $kernel->handle(Request::create('/api/_info/version'));
            $kernel->handle(Request::create('/api/_info/version'));

            static::assertSame(0, $counter->resetCount);
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * Same as a project config with `framework.http_cache.enabled: false`, but without `LONGRUNNING_ENVIRONMENT`.
     */
    public function testServicesAreNotResetWithoutLongrunningEnvironmentAndWithoutSymfonyHttpCache(): void
    {
        Feature::skipTestIfInActive('LONGRUNNING_ENVIRONMENT', $this);

        $kernel = KernelLifecycleManager::createKernel();
        $kernel->boot();

        try {
            static::assertFalse($kernel->getContainer()->has('http_cache'));

            $counter = $kernel->getContainer()->get(TestResetCounter::class);
            static::assertInstanceOf(TestResetCounter::class, $counter);

            Feature::withFeatureDisabled('LONGRUNNING_ENVIRONMENT', static function () use ($kernel): void {
                $kernel->handle(Request::create('/api/_info/version'));
                $kernel->handle(Request::create('/api/_info/version'));
            });

            static::assertSame(0, $counter->resetCount);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testHandleOnAnUnbootedKernelRegistersThePluginBundles(): void
    {
        $kernel = KernelLifecycleManager::createKernel();

        try {
            $kernel->handle(Request::create('/api/_info/version'));

            static::assertArrayHasKey($kernel->getPluginLoader()->getName(), $kernel->getBundles());
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * With `LONGRUNNING_ENVIRONMENT` Symfony's `http_cache` service must not exist, regardless of the project config:
     * `Symfony\Component\HttpKernel\Kernel::handle()` would route main requests through it, in front of Shopwell's own HTTP cache.
     */
    public function testSymfonyHttpCacheIsDisabled(): void
    {
        Feature::skipTestIfInActive('LONGRUNNING_ENVIRONMENT', $this);

        $container = KernelLifecycleManager::getKernel()->getContainer();

        static::assertFalse($container->has('http_cache'));
    }

    public function testHttpKernelIsDecoratedWithShopwellHttpCacheKernel(): void
    {
        $httpKernel = KernelLifecycleManager::getKernel()->getContainer()->get('http_kernel');

        static::assertInstanceOf(HttpCacheKernel::class, $httpKernel);
    }
}
