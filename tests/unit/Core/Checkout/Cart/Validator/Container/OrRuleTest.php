<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Validator\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\OrRule;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\Framework\Rule\SimpleRule;
use Shopwell\Core\Framework\Rule\UnknownConditionRule;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Assert\Serialization;
use Shopwell\Core\Test\Stub\Rule\FalseRule;
use Shopwell\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(OrRule::class)]
class OrRuleTest extends TestCase
{
    /**
     * @param list<Rule> $remainingRules
     */
    #[DataProvider('missingConditionCases')]
    public function testReplacesMissingConditionsAfterUnserialization(array $remainingRules, bool $expected): void
    {
        $original = new OrRule([new SimpleRule(), ...$remainingRules]);

        // Replace the class name with an unavailable name of the same length to keep the serialized data valid.
        $serialized = str_replace(SimpleRule::class, 'Shopwell\Core\Framework\Rule\AbsentRule', serialize($original));
        $rule = Serialization::assertUnserializedInstanceOf(OrRule::class, $serialized);
        static::assertInstanceOf(UnknownConditionRule::class, $rule->getRules()[0]);

        static::assertSame($expected, $rule->match(static::createStub(RuleScope::class)));
    }

    public static function missingConditionCases(): \Generator
    {
        yield 'only the missing condition remains' => [[], false];
        yield 'remaining condition matches' => [[new TrueRule()], true];
        yield 'remaining condition does not match' => [[new FalseRule()], false];
    }

    public function testTrue(): void
    {
        $rule = new OrRule([
            new TrueRule(),
            new FalseRule(),
        ]);

        static::assertTrue(
            $rule->match(
                new CheckoutRuleScope(
                    static::createStub(SalesChannelContext::class)
                )
            )
        );
    }

    public function testFalse(): void
    {
        $rule = new OrRule([
            new FalseRule(),
            new FalseRule(),
        ]);

        static::assertFalse(
            $rule->match(
                new CheckoutRuleScope(
                    static::createStub(SalesChannelContext::class)
                )
            )
        );
    }
}
