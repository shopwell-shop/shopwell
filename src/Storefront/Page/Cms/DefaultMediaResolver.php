<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Cms;

use Shopwell\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Asset\Packages;

#[Package('discovery')]
class DefaultMediaResolver extends AbstractDefaultMediaResolver
{
    private const CMS_SNIPPET_DEFAULT_MEDIA_NAME = 'component.cms.defaultMedia';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractDefaultMediaResolver $decorated,
        private readonly AbstractTranslator $translator,
        private readonly Packages $packages
    ) {
    }

    public function getDecorated(): AbstractDefaultMediaResolver
    {
        return $this->decorated;
    }

    public function getDefaultCmsMediaEntity(string $mediaAssetFilePath, string $snippetPath = self::CMS_SNIPPET_DEFAULT_MEDIA_NAME): ?MediaEntity
    {
        $media = $this->decorated->getDefaultCmsMediaEntity($mediaAssetFilePath);

        if (!$media) {
            return null;
        }

        $package = $this->packages->getPackage('asset');
        $snippetName = $snippetPath . '.' . $media->getFileName();

        // add translations to the media entity with a given snippet path
        $media->setTranslated([
            'title' => $this->translator->trans($snippetName . '.title'),
            'alt' => $this->translator->trans($snippetName . '.alt'),
        ]);

        // add the asset url
        $media->setUrl($package->getUrl($mediaAssetFilePath));

        return $media;
    }
}
