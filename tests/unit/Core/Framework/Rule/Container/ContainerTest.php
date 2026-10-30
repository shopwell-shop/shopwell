<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\AndRule;
use Shopwell\Core\Framework\Rule\Container\Container;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\Framework\Rule\SimpleRule;
use Shopwell\Core\Framework\Rule\UnknownConditionRule;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Assert\Serialization;
use Shopwell\Core\Test\Stub\Rule\FalseRule;
use Shopwell\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(Container::class)]
class ContainerTest extends TestCase
{
    public function testUnavailableChildrenAreReplacedDuringUnserialization(): void
    {
        $original = new AndRule([new SimpleRule(), new TrueRule()]);
        $serialized = str_replace(SimpleRule::class, 'Shopwell\Core\Framework\Rule\AbsentRule', serialize($original));
        $container = Serialization::assertUnserializedInstanceOf(AndRule::class, $serialized);

        static::assertEquals([new UnknownConditionRule(), new TrueRule()], $container->getRules());
        static::assertFalse($container->match(static::createStub(RuleScope::class)));
        static::assertEquals($container, Serialization::assertUnserializedInstanceOf(AndRule::class, serialize($container)));
    }

    public function testUnserializationPreservesSubclassAndInheritedState(): void
    {
        $original = new ContainerTestRule([new TrueRule()], 'plugin state');
        $original->addExtension('plugin', new ArrayStruct(['enabled' => true]));
        $serialized = serialize($original);

        $container = Serialization::assertUnserializedInstanceOf(ContainerTestRule::class, $serialized);

        static::assertEquals($original, $container);
        static::assertSame($serialized, serialize($container));
        static::assertTrue($container->match(static::createStub(RuleScope::class)));
    }

    public function testSubclassCanEvaluateRulesDirectlyAfterUnserialization(): void
    {
        $original = new ContainerTestRule([new SimpleRule()], 'plugin state');
        $serialized = str_replace(SimpleRule::class, 'Shopwell\Core\Framework\Rule\AbsentRule', serialize($original));

        $container = Serialization::assertUnserializedInstanceOf(ContainerTestRule::class, $serialized);

        static::assertInstanceOf(UnknownConditionRule::class, $container->getRules()[0]);
        static::assertFalse($container->match(static::createStub(RuleScope::class)));
    }

    public function testICanAddRulesAfterwards(): void
    {
        $rule = self::createContainer([new TrueRule()]);
        $rule->addRule(new TrueRule());

        static::assertEquals([new TrueRule(), new TrueRule()], $rule->getRules());

        $rule->setRules([new FalseRule()]);
        static::assertEquals([new FalseRule()], $rule->getRules());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the legacy __wakeup compatibility call.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyWakeupRunsAfterReplacingMissingChildren(): void
    {
        $original = new WakeupContainerTestRule([new SimpleRule()], 'plugin state');
        $serialized = str_replace(SimpleRule::class, 'Shopwell\Core\Framework\Rule\AbsentRule', serialize($original));

        $container = Serialization::assertUnserializedInstanceOf(WakeupContainerTestRule::class, $serialized);

        static::assertSame(1, $container->wakeupCalls);
        static::assertTrue($container->hadUnknownConditionOnWakeup);
    }

    public function testWakeupIsNotCalledInNextMajor(): void
    {
        $original = new WakeupContainerTestRule([new SimpleRule()], 'plugin state');
        $serialized = str_replace(SimpleRule::class, 'Shopwell\Core\Framework\Rule\AbsentRule', serialize($original));

        $container = Serialization::assertUnserializedInstanceOf(WakeupContainerTestRule::class, $serialized);

        static::assertSame(0, $container->wakeupCalls);
        static::assertFalse($container->hadUnknownConditionOnWakeup);
        static::assertInstanceOf(UnknownConditionRule::class, $container->getRules()[0]);
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

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ContainerTestRule extends Container
{
    final public const RULE_NAME = 'containerTestRule';

    /**
     * @param list<Rule> $rules
     */
    public function __construct(array $rules, private readonly string $pluginState)
    {
        parent::__construct($rules);
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig();
    }

    public function match(RuleScope $scope): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->match($scope)) {
                return false;
            }
        }

        return $this->pluginState === 'plugin state';
    }
}

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class WakeupContainerTestRule extends ContainerTestRule
{
    public int $wakeupCalls = 0;

    public bool $hadUnknownConditionOnWakeup = false;

    public function __wakeup(): void
    {
        ++$this->wakeupCalls;
        $this->hadUnknownConditionOnWakeup = $this->rules[0] instanceof UnknownConditionRule;
    }
}
