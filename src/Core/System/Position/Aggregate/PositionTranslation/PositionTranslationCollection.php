<?php declare(strict_types=1);

namespace Shopwell\Core\System\Position\Aggregate\PositionTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PositionTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class PositionTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'position_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PositionTranslationEntity::class;
    }
}
