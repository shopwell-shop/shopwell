<?php
declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionTranslationEntity::class;
    }
}
