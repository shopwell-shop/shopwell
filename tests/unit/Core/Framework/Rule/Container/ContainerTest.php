<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\Container;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopwell\Core\Test\Stub\Rule\FalseRule;
use Shopwell\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(Container::class)]
class ContainerTest extends TestCase
{
    public function testICanAddRulesAfterwards(): void
    {
        $rule = self::createContainer([new TrueRule()]);
        $rule->addRule(new TrueRule());

        static::assertEquals([new TrueRule(), new TrueRule()], $rule->getRules());

        $rule->setRules([new FalseRule()]);
        static::assertEquals([new FalseRule()], $rule->getRules());
    }

    public function testConstraintsAreStillTheSame(): void
    {
        static::assertEquals(
            ['rules' => [new ArrayOfType(Rule::class)]],
            self::createContainer()->getConstraints()
        );
    }

    /**
     * @param list<Rule> $rules
     */
    private static function createContainer(array $rules = []): Container
    {
        return new class($rules) extends Container {
            final public const RULE_NAME = 'containerTest';

            public function match(RuleScope $scope): bool
            {
                return true;
            }
        };
    }
}
