<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Installer\Requirements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Installer\Requirements\IniConfigReader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IniConfigReader::class)]
class IniConfigReaderTest extends TestCase
{
    #[DataProvider('configProvider')]
    public function testGet(string $key): void
    {
        $reader = new IniConfigReader();

        // The cast is intentional: ini_get() returns false for unknown keys, matching IniConfigReader's empty-string result.
        static::assertSame((string) \ini_get($key), $reader->get($key));
    }

    public static function configProvider(): \Generator
    {
        yield 'max_execution_time' => [
            'max_execution_time',
        ];

        yield 'memory_limit' => [
            'memory_limit',
        ];

        yield 'not set value' => [
            'not_existing_ini_value',
        ];
    }
}
