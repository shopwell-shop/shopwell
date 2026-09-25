<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\ActionButton;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<ActionButtonEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ActionButtonCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonEntity::class;
    }
}
