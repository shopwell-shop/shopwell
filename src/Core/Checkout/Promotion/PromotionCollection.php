<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionEntity::class;
    }
}
