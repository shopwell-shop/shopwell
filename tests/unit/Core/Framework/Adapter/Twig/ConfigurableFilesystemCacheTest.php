<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\ConfigurableFilesystemCache;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigurableFilesystemCache::class)]
class ConfigurableFilesystemCacheTest extends TestCase
{
    public function testGenerateKeyChangesHashWithTemplateScope(): void
    {
        $cache = new ConfigurableFilesystemCache('test', 0);

        $withoutScope = $cache->generateKey('foo', 'bar');
        static::assertSame($withoutScope, $cache->generateKey('foo', 'bar'));

        $cache->setTemplateScopes(['baz']);
        static::assertNotSame($withoutScope, $cache->generateKey('foo', 'bar'));
    }

    public function testGenerateKeyChangesHashWithOptionsHash(): void
    {
        $cache = new ConfigurableFilesystemCache('test', 0);

        $withoutScope = $cache->generateKey('foo', 'bar');
        static::assertSame($withoutScope, $cache->generateKey('foo', 'bar'));

        $cache->setConfigHash('hash');
        static::assertNotSame($withoutScope, $cache->generateKey('foo', 'bar'));
    }
}
