<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Cms;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class VimeoVideoCmsElementResolver extends YoutubeVideoCmsElementResolver
{
    public function getType(): string
    {
        return 'vimeo-video';
    }
}
