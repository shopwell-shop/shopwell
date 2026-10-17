<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\DaysSinceRule;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleException;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
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

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(RuleException::class);
        }

        $rule = new class extends DaysSinceRule {
            final public const RULE_NAME = 'daysSinceTest';

            protected function getDate(RuleScope $scope): ?\DateTimeInterface
            {
                return null;
            }

            protected function supportsScope(RuleScope $scope): bool
            {
                return true;
            }
        };
        $rule->assign([
            'operator' => Rule::OPERATOR_EQ,
            'daysPassed' => null,
        ]);

        $rule->match(new CheckoutRuleScope(static::createStub(SalesChannelContext::class)));
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
