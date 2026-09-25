<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupCountPackager;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceGrossPackager;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceNetPackager;
use Shopwell\Core\Checkout\Cart\LineItem\Group\ProductLineItemProvider;
use Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleLineItemMatcher;
use Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleMatcher;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceAscSorter;
use Shopwell\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceDescSorter;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopwell\Core\Checkout\Cart\Price\CashRounding;
use Shopwell\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Content\Rule\RuleEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\AndRule;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\Rule\FalseRule;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Fakes\FakeLineItemGroupSorter;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Fakes\FakeLineItemGroupTakeAllPackager;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Fakes\FakeSequenceSupervisor;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Fakes\FakeTakeAllRuleMatcher;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\LineItemGroupTestFixtureBehaviour;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\RulesTestFixtureBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemGroupBuilder::class)]
class LineItemGroupBuilderTest extends TestCase
{
    use LineItemGroupTestFixtureBehaviour;
    use LineItemTestFixtureBehaviour;
    use RulesTestFixtureBehaviour;

    private const KEY_PACKAGER_COUNT = 'COUNT';
    private const KEY_PRICE_UNIT_GROSS = 'PRICE_UNIT_GROSS';

    private const KEY_SORTER_PRICE_ASC = 'PRICE_ASC';
    private const KEY_SORTER_PRICE_DESC = 'PRICE_DESC';

    private LineItemGroupBuilder $lineItemGroupBuilder;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->context = static::createStub(SalesChannelContext::class);
        $this->context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $quantityPriceCalculator = $this->createQuantityPriceCalculator();

        $this->lineItemGroupBuilder = new LineItemGroupBuilder(
            new LineItemGroupServiceRegistry(
                [
                    new LineItemGroupCountPackager(),
                    new LineItemGroupUnitPriceGrossPackager(),
                    new LineItemGroupUnitPriceNetPackager(),
                ],
                [
                    new LineItemGroupPriceAscSorter(),
                    new LineItemGroupPriceDescSorter(),
                ]
            ),
            new AnyRuleMatcher(new AnyRuleLineItemMatcher()),
            new LineItemQuantitySplitter($quantityPriceCalculator),
            new ProductLineItemProvider()
        );
    }

    public function testSorterMatcherAndPackagerAreCalledInOrder(): void
    {
        $sequenceSupervisor = new FakeSequenceSupervisor();
        $takeAllPackager = new FakeLineItemGroupTakeAllPackager('FAKE-PACKAGER', $sequenceSupervisor);
        $sorter = new FakeLineItemGroupSorter('FAKE-SORTER', $sequenceSupervisor);
        $ruleMatcher = new FakeTakeAllRuleMatcher($sequenceSupervisor);

        $builder = new LineItemGroupBuilder(
            new LineItemGroupServiceRegistry([$takeAllPackager], [$sorter]),
            $ruleMatcher,
            new LineItemQuantitySplitter($this->createQuantityPriceCalculator()),
            new ProductLineItemProvider()
        );

        $cart = $this->buildCart(1);
        $group = $this->buildGroup('FAKE-PACKAGER', 2, 'FAKE-SORTER', new RuleCollection());

        $builder->findGroupPackages([$group], $cart, $this->context);

        static::assertSame(1, $sorter->getSequenceCount());
        static::assertSame(2, $ruleMatcher->getSequenceCount());
        static::assertSame(3, $takeAllPackager->getSequenceCount());
    }

    public function testCanOnlyBuildOneCountGroupWhenNotEnoughItemsExist(): void
    {
        $cart = $this->buildCart(3);

        $group = $this->buildGroup(self::KEY_PACKAGER_COUNT, 2, self::KEY_SORTER_PRICE_ASC, new RuleCollection());

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);

        static::assertCount(2, $result->getGroupTotalResult($group));
    }

    public function testBuildsAsManyCountGroupsAsPossible(): void
    {
        $cart = $this->buildCart(7);

        $group = $this->buildGroup(self::KEY_PACKAGER_COUNT, 2, self::KEY_SORTER_PRICE_ASC, new RuleCollection());

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);

        static::assertCount(6, $result->getGroupTotalResult($group));
    }

    public function testBuildsCountGroupsOnlyForProductsMatchingRule(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10);
        $item2 = $this->createProductItem(20, 10);
        $item3 = $this->createProductItem(50, 10);

        $item1->setReferencedId($item1->getId());
        $item2->setReferencedId($item2->getId());
        $item3->setReferencedId($item3->getId());

        $item1->setQuantity(10);
        $item2->setQuantity(10);
        $item3->setQuantity(10);

        $cart->addLineItems(new LineItemCollection([$item1, $item2, $item3]));

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload(new AndRule([
            $this->getProductsRule([$item1->getReferencedId(), $item2->getReferencedId()]),
        ]));

        $group = $this->buildGroup(
            self::KEY_PACKAGER_COUNT,
            5,
            self::KEY_SORTER_PRICE_DESC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);

        static::assertCount(4, $result->getGroupResult($group));
    }

    public function testBuildsCountGroupsOnlyForItemsMatchingListPriceRule(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10, 20);
        $item2 = $this->createProductItem(20, 10, 30);
        $item3 = $this->createProductItem(50, 10, 100);

        $item1->setReferencedId($item1->getId());
        $item2->setReferencedId($item2->getId());
        $item3->setReferencedId($item3->getId());

        $item1->setQuantity(10);
        $item2->setQuantity(10);
        $item3->setQuantity(10);

        $cart->addLineItems(new LineItemCollection([$item1, $item2, $item3]));

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload(new AndRule([
            $this->getLineItemListPriceRule(25),
        ]));

        $group = $this->buildGroup(
            self::KEY_PACKAGER_COUNT,
            5,
            self::KEY_SORTER_PRICE_DESC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);

        static::assertCount(4, $result->getGroupResult($group));
    }

    public function testItemNotMatchRule(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10, 20);

        $item1->setReferencedId($item1->getId());

        $item1->setQuantity(3);
        $item1->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([]), 3));

        $cart->addLineItems(new LineItemCollection([$item1]));

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload(new AndRule([new FalseRule()]));

        $group = $this->buildGroup(
            self::KEY_PRICE_UNIT_GROSS,
            50,
            self::KEY_SORTER_PRICE_ASC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);
        $groupCount = $result->getGroupResult($group);

        static::assertCount(0, $groupCount);
    }

    public function testNoItemMatchGroupDefinition(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10, 20);

        $item1->setReferencedId($item1->getId());

        $item1->setQuantity(3);
        $item1->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([]), 3));

        $cart->addLineItems(new LineItemCollection([$item1]));

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload(new AndRule());

        $group = $this->buildGroup(
            self::KEY_PRICE_UNIT_GROSS,
            50,
            self::KEY_SORTER_PRICE_ASC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);
        $groupCount = $result->getGroupResult($group);

        static::assertCount(0, $groupCount);
    }

    public function testOneItemShouldGroupsCorrectly(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10, 20);

        $item1->setReferencedId($item1->getId());

        $item1->setQuantity(3);
        $item1->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([]), 3));

        $cart->addLineItems(new LineItemCollection([$item1]));

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload(new AndRule());

        $group = $this->buildGroup(
            self::KEY_PRICE_UNIT_GROSS,
            30,
            self::KEY_SORTER_PRICE_ASC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);
        $groupCount = $result->getGroupResult($group);

        static::assertCount(1, $groupCount);

        static::assertInstanceOf(LineItemGroup::class, $groupCount[0]);
        $items = $groupCount[0]->getItems();
        static::assertCount(1, $items);
        static::assertInstanceOf(LineItemQuantity::class, $items[0]);
        static::assertSame($item1->getId(), $items[0]->getLineItemId());
        static::assertSame(3, $items[0]->getQuantity());
    }

    public function testShouldGroupsCorrectly(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10, 20);
        $item2 = $this->createProductItem(20, 10, 30);
        $item3 = $this->createProductItem(40, 10, 100);

        $item1->setReferencedId($item1->getId());
        $item2->setReferencedId($item2->getId());
        $item3->setReferencedId($item3->getId());

        $item1->setQuantity(3);
        $item2->setQuantity(7);
        $item3->setQuantity(5);
        $item1->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([]), 3));
        $item2->setPriceDefinition(new QuantityPriceDefinition(20, new TaxRuleCollection([]), 7));
        $item3->setPriceDefinition(new QuantityPriceDefinition(40, new TaxRuleCollection([]), 5));

        $cart->addLineItems(new LineItemCollection([$item1, $item2, $item3]));

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload(new AndRule());

        $group = $this->buildGroup(
            self::KEY_PRICE_UNIT_GROSS,
            70,
            self::KEY_SORTER_PRICE_ASC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);
        $groupCount = $result->getGroupResult($group);

        static::assertCount(4, $groupCount);

        static::assertInstanceOf(LineItemGroup::class, $groupCount[0]);
        $items = $groupCount[0]->getItems();
        static::assertCount(2, $items);
        static::assertInstanceOf(LineItemQuantity::class, $items[0]);
        static::assertInstanceOf(LineItemQuantity::class, $items[1]);
        static::assertSame($item1->getId(), $items[0]->getLineItemId());
        static::assertSame($item2->getId(), $items[1]->getLineItemId());
        static::assertSame(3, $items[0]->getQuantity());
        static::assertSame(2, $items[1]->getQuantity());

        static::assertInstanceOf(LineItemGroup::class, $groupCount[1]);
        $items = $groupCount[1]->getItems();
        static::assertCount(1, $items);
        static::assertInstanceOf(LineItemQuantity::class, $items[0]);
        static::assertSame($item2->getId(), $items[0]->getLineItemId());
        static::assertSame(4, $items[0]->getQuantity());

        static::assertInstanceOf(LineItemGroup::class, $groupCount[2]);
        $items = $groupCount[2]->getItems();
        static::assertCount(2, $items);
        static::assertInstanceOf(LineItemQuantity::class, $items[0]);
        static::assertInstanceOf(LineItemQuantity::class, $items[1]);
        static::assertSame($item2->getId(), $items[0]->getLineItemId());
        static::assertSame($item3->getId(), $items[1]->getLineItemId());
        static::assertSame(1, $items[0]->getQuantity());
        static::assertSame(2, $items[1]->getQuantity());

        static::assertInstanceOf(LineItemGroup::class, $groupCount[3]);
        $items = $groupCount[3]->getItems();
        static::assertCount(1, $items);
        static::assertInstanceOf(LineItemQuantity::class, $items[0]);
        static::assertSame($item3->getId(), $items[0]->getLineItemId());
        static::assertSame(2, $items[0]->getQuantity());
    }

    public function testBuildGroupCacheShouldWork(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10, 20);

        $item1->setReferencedId($item1->getId());

        $item1->setQuantity(3);
        $item1->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([]), 3));
        $cart->addLineItems(new LineItemCollection([$item1]));

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload(new AndRule());

        $group = $this->buildGroup(
            self::KEY_PRICE_UNIT_GROSS,
            30,
            self::KEY_SORTER_PRICE_ASC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);
        $groupCount = $result->getGroupResult($group);

        static::assertCount(1, $groupCount);

        static::assertInstanceOf(LineItemGroup::class, $groupCount[0]);
        $items = $groupCount[0]->getItems();
        static::assertCount(1, $items);
        static::assertInstanceOf(LineItemQuantity::class, $items[0]);
        static::assertSame($item1->getId(), $items[0]->getLineItemId());
        static::assertSame(3, $items[0]->getQuantity());

        $cachedResult = $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);

        static::assertEquals($result, $cachedResult);
    }

    public function testPackagerNotFound(): void
    {
        $cart = $this->buildCart(3);
        $group = $this->buildGroup('UNKNOWN', 2, self::KEY_SORTER_PRICE_ASC, new RuleCollection());

        $this->expectExceptionObject(CartException::lineItemGroupPackagerNotFoundException('UNKNOWN'));

        $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);
    }

    public function testSorterNotFound(): void
    {
        $cart = $this->buildCart(3);
        $group = $this->buildGroup(self::KEY_PACKAGER_COUNT, 2, 'UNKNOWN', new RuleCollection());

        $this->expectExceptionObject(CartException::lineItemGroupSorterNotFoundException('UNKNOWN'));

        $this->lineItemGroupBuilder->findGroupPackages([$group], $cart, $this->context);
    }

    private function buildCart(int $productCount): Cart
    {
        $products = [];

        for ($i = 1; $i <= $productCount; ++$i) {
            $products[] = $this->createProductItem(100, 0);
        }

        $cart = new Cart('token');
        $cart->addLineItems(new LineItemCollection($products));

        return $cart;
    }

    private function createQuantityPriceCalculator(): QuantityPriceCalculator
    {
        $priceRounding = new CashRounding();

        $taxCalculator = new TaxCalculator();

        return new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $priceRounding),
            new NetPriceCalculator($taxCalculator, $priceRounding),
        );
    }
}
