<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Rule\Fixture;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\DaysSinceRule;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class DaysSinceRuleFixture extends DaysSinceRule
{
    final public const RULE_NAME = 'fixtureDaysSince';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return null;
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return false;
    }
}
