<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Rule\Container;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\RuleScope;

#[Package('fundamentals@after-sales')]
class OrRule extends Container
{
    final public const RULE_NAME = 'orContainer';

    public function match(RuleScope $scope): bool
    {
        foreach ($this->rules as $rule) {
            if ($rule->match($scope)) {
                return true;
            }
        }

        return false;
    }
}
