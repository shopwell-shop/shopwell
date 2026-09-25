<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Cms;

use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractDefaultMediaResolver
{
    abstract public function getDecorated(): AbstractDefaultMediaResolver;

    abstract public function getDefaultCmsMediaEntity(string $mediaAssetFilePath): ?MediaEntity;
}
