<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\NotRule;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopwell\Core\Framework\Rule\RuleException;
use Shopwell\Core\Framework\Rule\SimpleRule;
use Shopwell\Core\Framework\Rule\UnknownConditionRule;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(NotRule::class)]
class NotRuleTest extends TestCase
{
    public function testMissingConditionAfterUnserializationDoesNotMatch(): void
    {
        $original = new NotRule([new SimpleRule()]);

        // Replace the class name with an unavailable name of the same length to keep the serialized data valid.
        $serialized = str_replace(SimpleRule::class, 'Shopwell\Core\Framework\Rule\AbsentRule', serialize($original));
        $rule = Serialization::assertUnserializedInstanceOf(NotRule::class, $serialized);
        static::assertInstanceOf(UnknownConditionRule::class, $rule->getRules()[0]);

        static::assertFalse($rule->match(new CheckoutRuleScope(static::createStub(SalesChannelContext::class))));
    }

    public function testUnknownConditionDoesNotMatch(): void
    {
        $rule = new NotRule([new UnknownConditionRule(['_name' => 'unknownPluginRule'])]);

        static::assertFalse($rule->match(new CheckoutRuleScope(static::createStub(SalesChannelContext::class))));
    }

    public function testUnsupportedValue(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(RuleException::class);
        }
        $rule = new NotRule();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $rule->match(new CheckoutRuleScope($salesChannelContext));
    }

    public function testAddRuleOnlyAllowsOneRule(): void
    {
        $this->expectException(\RuntimeException::class);

        $rule = new NotRule();
        $rule->addRule(new SimpleRule());
        $rule->addRule(new SimpleRule());
    }

    public function testSetRulesOnlyAllowsOneRule(): void
    {
        $this->expectException(\RuntimeException::class);

        $rule = new NotRule();
        $rule->setRules([new SimpleRule(), new SimpleRule()]);
    }
}
