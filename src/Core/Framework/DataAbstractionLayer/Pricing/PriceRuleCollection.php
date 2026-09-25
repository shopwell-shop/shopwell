<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Pricing;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PriceRuleEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PriceRuleCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_price_rule_collection';
    }

    protected function getExpectedClass(): string
    {
        return PriceRuleEntity::class;
    }
}
