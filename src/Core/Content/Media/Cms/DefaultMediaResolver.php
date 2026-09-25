<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Cms;

use League\Flysystem\FilesystemOperator;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('discovery')]
class DefaultMediaResolver extends AbstractDefaultMediaResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function getDecorated(): AbstractDefaultMediaResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function getDefaultCmsMediaEntity(string $mediaAssetFilePath): ?MediaEntity
    {
        if (!$this->filesystem->fileExists($mediaAssetFilePath)) {
            return null;
        }

        $mimeType = $this->filesystem->mimeType($mediaAssetFilePath);
        $pathInfo = pathinfo($mediaAssetFilePath);

        if (!$mimeType || !\array_key_exists('extension', $pathInfo)) {
            return null;
        }

        $media = new MediaEntity();
        $media->setFileName($pathInfo['filename']);
        $media->setMimeType($mimeType);
        $media->setFileExtension($pathInfo['extension']);

        return $media;
    }
}
