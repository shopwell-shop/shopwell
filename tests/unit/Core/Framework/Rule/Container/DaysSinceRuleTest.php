<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\DaysSinceRule;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Tests\Unit\Core\Framework\Rule\Fixture\DaysSinceRuleFixture;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(DaysSinceRule::class)]
class DaysSinceRuleTest extends TestCase
{
    private DaysSinceRuleFixture $rule;

    protected function setUp(): void
    {
        $this->rule = new DaysSinceRuleFixture();
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('daysPassed', $constraints, 'daysPassed constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::float(), $constraints['daysPassed']);
        static::assertEquals(RuleConstraints::numericOperators(), $constraints['operator']);
    }

    public function testRuleConfig(): void
    {
        $config = $this->rule->getConfig()->getData();

        static::assertArrayHasKey('operatorSet', $config);
        static::assertArrayHasKey('fields', $config);

        $operators = RuleConfig::OPERATOR_SET_NUMBER;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $config['operatorSet']);

        static::assertSame([
            'name' => 'daysPassed',
            'type' => 'float',
            'config' => [
                'unit' => 'time',
                'digits' => RuleConfig::DEFAULT_DIGITS,
            ],
        ], $config['fields']['daysPassed']);
    }
}
