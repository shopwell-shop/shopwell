<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Plugin\KernelPluginLoader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopwell\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class DbalKernelPluginLoaderTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    public function testLoadNoPlugins(): void
    {
        $loader = new DbalKernelPluginLoader($this->classLoader, null, static::getContainer()->get(Connection::class));
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertCount(0, $loader->getPluginInfos());
        static::assertCount(0, $loader->getPluginInstances()->all());
    }

    public function testLoadNoInit(): void
    {
        $plugin = $this->getActivePlugin();
        $this->insertPlugin($plugin);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, static::getContainer()->get(Connection::class));
        static::assertCount(0, $loader->getPluginInfos());
    }

    public function testLoadPlugins(): void
    {
        $plugin = $this->getActivePlugin();
        $this->insertPlugin($plugin);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, static::getContainer()->get(Connection::class));
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertNotCount(0, $loader->getPluginInfos());
    }
}
