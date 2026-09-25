<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\FlowActionTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppFlowActionTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppFlowActionTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppFlowActionTranslationEntity::class;
    }
}
