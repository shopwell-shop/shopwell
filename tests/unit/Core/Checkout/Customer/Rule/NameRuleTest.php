<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Rule\NameRule;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(NameRule::class)]
#[Group('rules')]
class NameRuleTest extends TestCase
{
    private NameRule $rule;

    protected function setUp(): void
    {
        $this->rule = new NameRule();
    }

    public function testName(): void
    {
        static::assertSame('customerName', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('name', $constraints, 'Name constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals(RuleConstraints::stringOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::string(), $constraints['name']);
    }

    #[DataProvider('getMatchCustomerNameValues')]
    public function testNameRuleMatching(bool $expected, ?string $customerName, ?string $ruleNameValue, string $operator): void
    {
        $customer = new CustomerEntity();
        $customer->setName($customerName ?? '');

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $cart = new Cart('test');
        $scope = new CartRuleScope($cart, $context);

        $this->rule->assign(['name' => $ruleNameValue, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);

        static::assertSame($expected, $isMatching);
    }

    public function testConfig(): void
    {
        $config = (new NameRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    public function testCustomerNotExist(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            static::createStub(SalesChannelContext::class)
        );

        $this->rule->assign(['name' => 'shopwell', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testCustomerNotExistAndOperatorEmpty(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            static::createStub(SalesChannelContext::class)
        );

        $this->rule->assign(['name' => 'shopwell', 'operator' => Rule::OPERATOR_EMPTY]);
        static::assertTrue($this->rule->match($scope));
    }

    public function testInvalidName(): void
    {
        $customer = new CustomerEntity();
        $customer->setName('shopwell');

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $cart = new Cart('test');
        $scope = new CartRuleScope($cart, $context);

        $this->rule->assign(['name' => true, 'operator' => Rule::OPERATOR_EQ]);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(CustomerException::class);
        }
        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['name' => 'shopwell', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{bool, string|null, string|null, string}>
     */
    public static function getMatchCustomerNameValues(): array
    {
        return [
            'EQ - true' => [true, 'shopwell', 'shopwell', Rule::OPERATOR_EQ],
            'EQ - false' => [false, 'shopwell', 'shopwellAG', Rule::OPERATOR_EQ],
            'EQ(CASE) - true' => [true, 'shopwell', 'Shopwell', Rule::OPERATOR_EQ],
            'NEQ - true' => [true, 'shopwell', 'shopwellAG', Rule::OPERATOR_NEQ],
            'NEQ - false' => [false, 'shopwell', 'shopwell', Rule::OPERATOR_NEQ],
            'NEQ(CASE) - false' => [false, 'shopwell', 'Shopwell', Rule::OPERATOR_NEQ],
            'EMPTY - false' => [false, 'shopwell', null, Rule::OPERATOR_EMPTY],
            'EMPTY - true' => [true, null, null, Rule::OPERATOR_EMPTY],
        ];
    }
}
