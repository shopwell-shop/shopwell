<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ContentSystem;

use Shopwell\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Test\TestKernel;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
class PersistentSystemCacheTestKernel extends TestKernel
{
    public static function bootForTest(): void
    {
        if (KernelLifecycleManager::getKernel() instanceof self) {
            return;
        }

        $previousKernelClass = KernelFactory::$kernelClass;
        KernelFactory::$kernelClass = self::class;

        try {
            KernelLifecycleManager::bootKernel(cacheId: Uuid::randomHex());
        } finally {
            KernelFactory::$kernelClass = $previousKernelClass;
        }
    }

    public function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);
        $container->loadFromExtension('framework', [
            'cache' => ['system' => 'cache.adapter.filesystem'],
        ]);
    }
}
