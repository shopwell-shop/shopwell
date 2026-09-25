<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionSetGroup;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionSetGroupEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionSetGroupCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_set_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionSetGroupEntity::class;
    }
}
