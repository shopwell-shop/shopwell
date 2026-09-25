<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\Rule;

use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
class CountingTrueRule extends TrueRule
{
    public int $matchCount = 0;

    public function match(RuleScope $matchContext): bool
    {
        ++$this->matchCount;

        return parent::match($matchContext);
    }

    public function getApiAlias(): string
    {
        return 'rule_counting_true';
    }
}
