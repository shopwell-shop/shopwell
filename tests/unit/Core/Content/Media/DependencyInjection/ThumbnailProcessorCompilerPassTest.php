<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\DependencyInjection\ThumbnailProcessorCompilerPass;
use Shopwell\Core\Content\Media\Thumbnail\Processor\GdImageThumbnailProcessor;
use Shopwell\Core\Content\Media\Thumbnail\Processor\ImagickThumbnailProcessor;
use Shopwell\Core\Content\Media\Thumbnail\Processor\ThumbnailProcessorInterface;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThumbnailProcessorCompilerPass::class)]
class ThumbnailProcessorCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        if (!\extension_loaded('imagick')) {
            static::markTestSkipped('Imagick is not installed');
        }

        $container = new ContainerBuilder();

        $container->setParameter('shopware.media.thumbnail_processor', 'imagick');
        $container->setDefinition(ThumbnailProcessorInterface::class, new Definition(GdImageThumbnailProcessor::class));

        $pass = new ThumbnailProcessorCompilerPass();
        $pass->process($container);

        $defintion = $container->getDefinition(ThumbnailProcessorInterface::class);

        static::assertSame(ImagickThumbnailProcessor::class, $defintion->getClass());
    }
}
