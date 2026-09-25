<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\DataResolver;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<FieldConfig>
 */
#[Package('discovery')]
class FieldConfigCollection extends Collection
{
    /**
     * @param FieldConfig $element
     */
    public function add($element): void
    {
        $this->set($element->getName(), $element);
    }

    /**
     * @param string|int $key
     * @param FieldConfig $element
     */
    public function set($key, $element): void
    {
        parent::set($element->getName(), $element);
    }

    public function getApiAlias(): string
    {
        return 'cms_data_resolver_field_config_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return FieldConfig::class;
    }
}
