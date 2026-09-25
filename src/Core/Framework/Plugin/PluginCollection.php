<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PluginEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'plugin_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginEntity::class;
    }
}
