<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Core\Params;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
enum UrlParamsSource
{
    case MEDIA;
    case THUMBNAIL;
}
