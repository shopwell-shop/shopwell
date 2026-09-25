<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Cms\Type;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class ImageGalleryTypeDataResolver extends ImageSliderTypeDataResolver
{
    public function getType(): string
    {
        return 'image-gallery';
    }
}
