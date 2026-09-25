<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\Symfony;

use Composer\Autoload\ClassLoader;
use Shopwell\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopwell\Core\Kernel;
use Shopwell\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class StubKernel extends Kernel
{
    /**
     * @param list<BundleInterface> $bundles
     */
    public function __construct(array $bundles = [])
    {
        parent::__construct(
            'test',
            true,
            new ComposerPluginLoader(new ClassLoader(__DIR__)),
            '',
            '',
            new FakeConnection([]),
            __DIR__
        );

        foreach ($bundles as $bundle) {
            $this->bundles[$bundle->getName()] = $bundle;
        }
    }

    public function registerBundles(): iterable
    {
        return $this->bundles;
    }
}
