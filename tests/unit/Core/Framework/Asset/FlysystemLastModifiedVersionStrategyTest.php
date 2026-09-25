<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Asset;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Asset\FlysystemLastModifiedVersionStrategy;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FlysystemLastModifiedVersionStrategy::class)]
class FlysystemLastModifiedVersionStrategyTest extends TestCase
{
    private Filesystem $fs;

    private UrlPackage $asset;

    private FlysystemLastModifiedVersionStrategy $strategy;

    protected function setUp(): void
    {
        $this->fs = new Filesystem(new InMemoryFilesystemAdapter());
        $this->strategy = new FlysystemLastModifiedVersionStrategy('test', $this->fs, new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter()));
        $this->asset = new UrlPackage(['http://shopwell.cn'], $this->strategy);
    }

    public function testNonExistentFile(): void
    {
        $url = $this->asset->getUrl('test');
        static::assertSame('http://shopwell.cn/test', $url);
    }

    public function testExistsFile(): void
    {
        $this->fs->write('testFile', 'yea');
        $lastModified = (string) $this->fs->lastModified('testFile');
        $url = $this->asset->getUrl('testFile');
        static::assertSame('http://shopwell.cn/testFile?' . $lastModified, $url);
    }

    public function testApplyDoesSameAsGetVersion(): void
    {
        static::assertSame($this->strategy->getVersion('foo'), $this->strategy->getVersion('foo'));
    }

    public function testFolder(): void
    {
        $this->fs->write('folder/file', 'test');

        static::assertSame('http://shopwell.cn/folder', $this->asset->getUrl('folder'));
        static::assertSame('http://shopwell.cn/not_existing/bla', $this->asset->getUrl('not_existing/bla'));
        static::assertSame('http://shopwell.cn/folder', $this->asset->getUrl('folder'));
    }

    public function testWithEmptyString(): void
    {
        $fs = $this->createMock(FilesystemOperator::class);
        $fs->expects($this->never())->method('lastModified');

        $strategy = new FlysystemLastModifiedVersionStrategy('test', $fs, new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter()));

        static::assertSame('', $strategy->getVersion(''));
    }
}
