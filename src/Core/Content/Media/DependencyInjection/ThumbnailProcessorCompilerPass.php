<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\DependencyInjection;

use Shopwell\Core\Content\Media\Thumbnail\Processor\ImagickThumbnailProcessor;
use Shopwell\Core\Content\Media\Thumbnail\Processor\ThumbnailProcessorInterface;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('discovery')]
class ThumbnailProcessorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (
            $container->getParameter('shopwell.media.thumbnail_processor') === 'imagick'
            && \extension_loaded('imagick')
        ) {
            $container->getDefinition(ThumbnailProcessorInterface::class)
                ->setClass(ImagickThumbnailProcessor::class);
        }
    }
}
