<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * Pseudo immutable collection
 *
 * @extends Collection<PluginCategoryStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class PluginCategoryCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'store_category_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginCategoryStruct::class;
    }
}
