<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Rule\Aggregate\RuleCondition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<RuleConditionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class RuleConditionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'rule_condition_collection';
    }

    protected function getExpectedClass(): string
    {
        return RuleConditionEntity::class;
    }
}
