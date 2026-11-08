<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemLayoutPresetEntity>
 */
#[Package('framework')]
class AppContentSystemLayoutPresetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemLayoutPresetEntity::class;
    }
}
