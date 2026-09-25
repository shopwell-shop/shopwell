<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Rule\Container;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * AndRule returns true, if all child-rules are true
 */
#[Package('fundamentals@after-sales')]
class AndRule extends Container
{
    final public const RULE_NAME = 'andContainer';

    public function match(RuleScope $scope): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->match($scope)) {
                return false;
            }
        }

        return true;
    }
}
