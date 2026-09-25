<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\SystemConfig\CachedSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\ConfiguredSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\SystemConfigLoader;

/**
 * @internal
 */
#[Package('framework')]
class MemoizedSystemConfigLoaderTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServiceDecorationChainPriority(): void
    {
        $service = static::getContainer()->get(SystemConfigLoader::class);

        static::assertInstanceOf(MemoizedSystemConfigLoader::class, $service);
        static::assertInstanceOf(ConfiguredSystemConfigLoader::class, $service->getDecorated());
        static::assertInstanceOf(CachedSystemConfigLoader::class, $service->getDecorated()->getDecorated());
        static::assertInstanceOf(SystemConfigLoader::class, $service->getDecorated()->getDecorated()->getDecorated());
    }
}
