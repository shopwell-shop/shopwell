<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Cms;

use Shopwell\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryNameCmsElementResolver extends TextCmsElementResolver
{
    public function getType(): string
    {
        return 'category-name';
    }
}
