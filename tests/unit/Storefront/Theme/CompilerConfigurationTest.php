<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\CompilerConfiguration;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CompilerConfiguration::class)]
class CompilerConfigurationTest extends TestCase
{
    public function testGetNotSetValue(): void
    {
        $config = new CompilerConfiguration([]);

        static::assertNull($config->getValue('test'));
    }

    public function testGetSetValue(): void
    {
        $config = new CompilerConfiguration([
            'test' => 'value',
        ]);

        static::assertSame('value', $config->getValue('test'));
    }

    public function testGetWholeConfiguration(): void
    {
        $config = new CompilerConfiguration([
            'test' => 'value',
        ]);

        static::assertSame([
            'test' => 'value',
        ], $config->getConfiguration());
    }
}
