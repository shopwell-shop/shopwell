<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\Rule;

use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleScope;

class FalseRule extends Rule
{
    final public const RULE_NAME = 'false';

    public function match(RuleScope $matchContext): bool
    {
        return false;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
