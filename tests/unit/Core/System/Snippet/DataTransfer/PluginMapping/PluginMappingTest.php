<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\DataTransfer\PluginMapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\DataTransfer\PluginMapping\PluginMapping;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PluginMapping::class)]
class PluginMappingTest extends TestCase
{
    public function testConstruction(): void
    {
        $pluginMapping = new PluginMapping('TestPlugin', 'TestTranslation');

        static::assertSame('TestPlugin', $pluginMapping->pluginName);
        static::assertSame('TestTranslation', $pluginMapping->snippetName);
    }
}
