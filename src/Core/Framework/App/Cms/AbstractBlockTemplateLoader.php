<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Cms;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
abstract class AbstractBlockTemplateLoader
{
    abstract public function getTemplateForBlock(CmsExtensions $cmsExtensions, string $blockName): string;

    abstract public function getStylesForBlock(CmsExtensions $cmsExtensions, string $blockName): string;
}
