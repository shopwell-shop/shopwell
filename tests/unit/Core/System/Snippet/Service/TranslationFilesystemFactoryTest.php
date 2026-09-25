<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Service\TranslationFilesystemFactory;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationFilesystemFactory::class)]
class TranslationFilesystemFactoryTest extends TestCase
{
    public function testUsesPrivateFilesystemByDefault(): void
    {
        $privateFilesystem = static::createStub(FilesystemOperator::class);
        $filesystemFactory = $this->createMock(FilesystemFactory::class);
        $filesystemFactory->expects($this->never())->method('privateFactory');

        $factory = new TranslationFilesystemFactory($privateFilesystem, $filesystemFactory, '/project', false);

        static::assertSame($privateFilesystem, $factory->create());
    }

    public function testUsesLocalFilesystemWhenConfigured(): void
    {
        $privateFilesystem = static::createStub(FilesystemOperator::class);
        $localFilesystem = static::createStub(FilesystemOperator::class);
        $filesystemFactory = $this->createMock(FilesystemFactory::class);
        $filesystemFactory->expects($this->once())
            ->method('privateFactory')
            ->with([
                'type' => 'local',
                'config' => ['root' => '/project/var'],
            ])
            ->willReturn($localFilesystem);

        $factory = new TranslationFilesystemFactory($privateFilesystem, $filesystemFactory, '/project', true);

        static::assertSame($localFilesystem, $factory->create());
    }
}
