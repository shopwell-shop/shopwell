<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\ConfiguredSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\SymfonySystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfiguredSystemConfigLoader::class)]
class ConfiguredSystemConfigLoaderTest extends TestCase
{
    public function testDecoration(): void
    {
        $configLoader = $this->createMock(AbstractSystemConfigLoader::class);

        $config = new SymfonySystemConfigService(['default' => ['test.key' => 'true']]);

        $decorator = new ConfiguredSystemConfigLoader($configLoader, $config);

        $configLoader->expects($this->once())
            ->method('load')
            ->willReturn(['test' => ['key' => 'false']]);

        static::assertSame(['test' => ['key' => 'true']], $decorator->load(null));
    }
}
