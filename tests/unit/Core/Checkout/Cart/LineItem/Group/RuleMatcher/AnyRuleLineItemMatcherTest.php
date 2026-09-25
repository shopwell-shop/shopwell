<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\RuleMatcher;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AbstractAnyRuleLineItemMatcher;
use Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleLineItemMatcher;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\RulesTestFixtureBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AnyRuleLineItemMatcher::class)]
class AnyRuleLineItemMatcherTest extends TestCase
{
    use LineItemTestFixtureBehaviour;
    use RulesTestFixtureBehaviour;

    private AbstractAnyRuleLineItemMatcher $matcher;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->matcher = new AnyRuleLineItemMatcher();
        $this->context = Generator::generateSalesChannelContext();
    }

    #[DataProvider('lineItemProvider')]
    public function testMatching(bool $withRules, bool $diffrentId, bool $expected): void
    {
        $lineItem = $this->createProductItem(50, 10);
        $lineItem->setReferencedId($lineItem->getId());

        $ruleCollection = new RuleCollection();

        if ($withRules === true) {
            $matchId = $diffrentId === true ? Uuid::randomHex() : $lineItem->getId();

            $ruleCollection->add($this->buildRuleEntity(
                $this->getProductsRule([$matchId])
            ));
        }

        $group = new LineItemGroupDefinition('test', 'COUNT', 1, 'PRICE_ASC', $ruleCollection);

        static::assertSame($expected, $this->matcher->isMatching($group, $lineItem, $this->context));
    }

    /**
     * @return iterable<string, array<bool>>
     */
    public static function lineItemProvider(): iterable
    {
        yield 'Matching line item not in group with rules' => [true, true, false];
        yield 'Matching line item not in group without rules' => [false, false, true];
        yield 'Matching line item in group with rules' => [true, false, true];
    }

    public function testItThrowsDecorationPatternException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->matcher->getDecorated();
    }
}
