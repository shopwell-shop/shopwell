<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PluginRegionStruct extends Struct
{
    protected PluginCategoryCollection $categories;

    /**
     * @param iterable<PluginCategoryStruct> $categories
     */
    public function __construct(
        protected string $name,
        protected string $label,
        iterable $categories,
    ) {
        $this->categories = new PluginCategoryCollection($categories);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCategories(): PluginCategoryCollection
    {
        return $this->categories;
    }

    public function getApiAlias(): string
    {
        return 'store_plugin_region';
    }
}
