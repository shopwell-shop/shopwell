<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Rule\CartHasDeliveryFreeItemRule;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Checkout\CartRuleFixture;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[Group('rules')]
class CartHasFreeDeliveryItemRuleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testIfShippingFreeLineItemsAreCaught(): void
    {
        $lineItemCollection = new LineItemCollection([
            CartRuleFixture::createLineItemWithDeliveryInfo(false),
            CartRuleFixture::createLineItemWithDeliveryInfo(true),
        ]);

        $cart = CartRuleFixture::createCart($lineItemCollection);

        $match = (new CartHasDeliveryFreeItemRule())
            ->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class)));

        static::assertTrue($match);
    }

    public function testIfShippingFreeNestedLineItemsAreCaught(): void
    {
        $childLineItemCollection = new LineItemCollection([
            CartRuleFixture::createLineItemWithDeliveryInfo(false),
            CartRuleFixture::createLineItemWithDeliveryInfo(true),
        ]);

        $containerLineItem = CartRuleFixture::createContainerLineItem($childLineItemCollection);

        $cart = CartRuleFixture::createCart(new LineItemCollection([$containerLineItem]));

        $match = (new CartHasDeliveryFreeItemRule())
            ->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class)));

        static::assertTrue($match);
    }

    public function testNotContainsFreeDeliveryItems(): void
    {
        $lineItemCollection = new LineItemCollection([
            CartRuleFixture::createLineItemWithDeliveryInfo(false),
        ]);

        $cart = CartRuleFixture::createCart($lineItemCollection);

        $match = (new CartHasDeliveryFreeItemRule())
            ->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class)));

        static::assertFalse($match);
    }

    public function testEmptyDeliveryItems(): void
    {
        $cart = CartRuleFixture::createCart(new LineItemCollection());

        $match = (new CartHasDeliveryFreeItemRule())
            ->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class)));

        static::assertFalse($match);

        $match = (new CartHasDeliveryFreeItemRule())->assign(['allowed' => false])
            ->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class)));

        static::assertTrue($match);
    }

    public function testNotContainsFreeDeliveryItemsMatchesNotAllowed(): void
    {
        $lineItemCollection = new LineItemCollection([
            CartRuleFixture::createLineItemWithDeliveryInfo(false),
        ]);

        $cart = CartRuleFixture::createCart($lineItemCollection);

        $match = (new CartHasDeliveryFreeItemRule())->assign(['allowed' => false])
            ->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class)));

        static::assertTrue($match);
    }

    public function testNotContainsFreeDeliveryItemsWithDeliveryFreeItem(): void
    {
        $lineItemCollection = new LineItemCollection([
            CartRuleFixture::createLineItemWithDeliveryInfo(false),
            CartRuleFixture::createLineItemWithDeliveryInfo(true),
        ]);

        $cart = CartRuleFixture::createCart($lineItemCollection);

        $match = (new CartHasDeliveryFreeItemRule())->assign(['allowed' => false])
            ->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class)));

        static::assertFalse($match);
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();

        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new CartHasDeliveryFreeItemRule())->getName(),
                'ruleId' => $ruleId,
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
    }

    #[DataProvider('getLineItemFreeDeliveryTestData')]
    public function testLineItemIsFreeDelivery(bool $ruleActive, bool $isFreeDelivery, bool $expected): void
    {
        $lineItem = CartRuleFixture::createLineItemWithDeliveryInfo($isFreeDelivery);

        $match = (new CartHasDeliveryFreeItemRule())->assign(['allowed' => $ruleActive])
            ->match(new LineItemScope($lineItem, static::createStub(SalesChannelContext::class)));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<bool>>
     */
    public static function getLineItemFreeDeliveryTestData(): array
    {
        return [
            'rule yes / shipping free yes' => [true, true, true],
            'rule yes / shipping free no' => [true, false, false],
            'rule no / shipping free yes' => [false, true, false],
            'rule no / shipping free no' => [false, false, true],
        ];
    }
}
