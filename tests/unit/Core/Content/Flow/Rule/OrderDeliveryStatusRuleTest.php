<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Flow\Rule\FlowRuleScope;
use Shopwell\Core\Content\Flow\Rule\OrderDeliveryStatusRule;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderDeliveryStatusRule::class)]
#[Group('rules')]
class OrderDeliveryStatusRuleTest extends TestCase
{
    private OrderDeliveryStatusRule $rule;

    protected function setUp(): void
    {
        $this->rule = new OrderDeliveryStatusRule();
    }

    public function testName(): void
    {
        static::assertSame('orderDeliveryStatus', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('stateIds', $constraints, 'stateIds constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::uuids(), $constraints['stateIds']);
        static::assertEquals(RuleConstraints::uuidOperators(false), $constraints['operator']);
    }

    /**
     * @param list<string> $selectedOrderStateIds
     */
    #[DataProvider('getMatchingValues')]
    public function testOrderDeliveryStatusRuleMatching(bool $expected, string $orderStateId, array $selectedOrderStateIds, string $operator): void
    {
        $orderDeliveryId = Uuid::randomHex();

        $orderDeliveryCollection = new OrderDeliveryCollection();
        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setId($orderDeliveryId);
        $orderDelivery->setStateId($orderStateId);
        $orderDeliveryCollection->add($orderDelivery);
        $order = new OrderEntity();
        $order->setDeliveries($orderDeliveryCollection);

        if (Feature::isActive('v6.8.0.0')) {
            $order->setPrimaryOrderDeliveryId($orderDeliveryId);
            $order->setPrimaryOrderDelivery($orderDelivery);
        }

        $scope = new FlowRuleScope(
            $order,
            new Cart('test'),
            static::createStub(SalesChannelContext::class)
        );

        $this->rule->assign(['stateIds' => $selectedOrderStateIds, 'operator' => $operator]);
        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['salutationIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testDeliveriesEmpty(): void
    {
        $order = new OrderEntity();
        $order->setDeliveries(new OrderDeliveryCollection());
        $orderDeliveryCollection = new OrderDeliveryCollection();
        $order->setDeliveries($orderDeliveryCollection);
        $scope = new FlowRuleScope(
            $order,
            new Cart('test'),
            static::createStub(SalesChannelContext::class)
        );

        $this->rule->assign(['stateIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testConfig(): void
    {
        $config = (new OrderDeliveryStatusRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{bool, string, list<string>, string}>
     */
    public static function getMatchingValues(): array
    {
        $id = Uuid::randomHex();

        return [
            'ONE OF - true' => [true, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_EQ],
            'ONE OF - false' => [false, $id, [Uuid::randomHex()], Rule::OPERATOR_EQ],
            'NONE OF - true' => [true, $id, [Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'NONE OF - false' => [false, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_NEQ],
        ];
    }
}
