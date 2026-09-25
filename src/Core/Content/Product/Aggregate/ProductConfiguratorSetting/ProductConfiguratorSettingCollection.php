<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductConfiguratorSetting;

use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopwell\Core\Content\Property\PropertyGroupCollection;
use Shopwell\Core\Content\Property\PropertyGroupEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductConfiguratorSettingEntity>
 */
#[Package('inventory')]
class ProductConfiguratorSettingCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(static fn (ProductConfiguratorSettingEntity $productConfigurator) => $productConfigurator->getProductId());
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(static fn (ProductConfiguratorSettingEntity $productConfigurator) => $productConfigurator->getProductId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getOptionIds(): array
    {
        return $this->fmap(static fn (ProductConfiguratorSettingEntity $productConfigurator) => $productConfigurator->getOptionId());
    }

    public function filterByOptionId(string $id): self
    {
        return $this->filter(static fn (ProductConfiguratorSettingEntity $productConfigurator) => $productConfigurator->getOptionId() === $id);
    }

    public function getOptions(): PropertyGroupOptionCollection
    {
        return new PropertyGroupOptionCollection(
            $this->fmap(static fn (ProductConfiguratorSettingEntity $productConfigurator) => $productConfigurator->getOption())
        );
    }

    public function getGroupedOptions(): PropertyGroupCollection
    {
        $groups = new PropertyGroupCollection();

        foreach ($this->getIterator() as $element) {
            if (!$element->getOption()) {
                continue;
            }

            if ($groups->has($element->getOption()->getGroupId())) {
                $group = $groups->get($element->getOption()->getGroupId());
            } else {
                $group = PropertyGroupEntity::createFrom($element->getOption()->getGroup() ?? new PropertyGroupEntity());

                $groups->add($group);

                $group->setOptions(new PropertyGroupOptionCollection());
            }

            if ($group->getOptions()) {
                $group->getOptions()->add($element->getOption());
            }
        }

        return $groups;
    }

    public function getByOptionId(string $optionId): ?ProductConfiguratorSettingEntity
    {
        foreach ($this->getIterator() as $element) {
            if ($element->getOptionId() === $optionId) {
                return $element;
            }
        }

        return null;
    }

    public function getApiAlias(): string
    {
        return 'product_configurator_settings_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductConfiguratorSettingEntity::class;
    }
}
