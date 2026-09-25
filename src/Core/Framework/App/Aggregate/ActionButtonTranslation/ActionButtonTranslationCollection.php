<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<ActionButtonTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ActionButtonTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonTranslationEntity::class;
    }
}
