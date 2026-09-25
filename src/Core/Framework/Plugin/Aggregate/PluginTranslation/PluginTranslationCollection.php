<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Aggregate\PluginTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PluginTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'plugin_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginTranslationEntity::class;
    }
}
