<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Rule\CustomerSalutationRule;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CustomerSalutationRule::class)]
#[Group('rules')]
class CustomerSalutationRuleTest extends TestCase
{
    private CustomerSalutationRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CustomerSalutationRule();
    }

    public function testName(): void
    {
        static::assertSame('customerSalutation', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('genderCodes', $constraints, 'gender constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::stringArray(), $constraints['genderCodes']);
        static::assertEquals(RuleConstraints::stringOperators(), $constraints['operator']);
    }

    /**
     * @param list<string> $genderCodes
     */
    #[DataProvider('getMatchCustomerSalutationValues')]
    public function testCustomerSalutationRuleMatching(bool $expected, ?string $customerGender, array $genderCodes, string $operator): void
    {
        $customer = new CustomerEntity();

        if ($customerGender) {
            $customer->setGender($customerGender);
        }

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['genderCodes' => $genderCodes, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);
        static::assertSame($expected, $isMatching);
    }

    public function testConfig(): void
    {
        $config = (new CustomerSalutationRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    public function testCustomerNotLoggedInReturnsFalse(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            static::createStub(SalesChannelContext::class)
        );

        $this->rule->assign(['genderCodes' => [DictionaryEntryDefinition::MALE], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testCustomerHasNoSalutationSpecifiedReturnsFalse(): void
    {
        $customer = new CustomerEntity();

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['genderCodes' => [DictionaryEntryDefinition::MALE], 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['genderCodes' => [DictionaryEntryDefinition::MALE], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{bool, string|null, list<string>, string}>
     */
    public static function getMatchCustomerSalutationValues(): array
    {
        $gender = DictionaryEntryDefinition::MALE;

        return [
            'ONE OF - true' => [true, $gender, [$gender, DictionaryEntryDefinition::FEMALE], Rule::OPERATOR_EQ],
            'ONE OF - false' => [false, $gender, [DictionaryEntryDefinition::FEMALE], Rule::OPERATOR_EQ],
            'NONE OF - true' => [true, $gender, [DictionaryEntryDefinition::FEMALE], Rule::OPERATOR_NEQ],
            'NONE OF - false' => [false, $gender, [$gender, DictionaryEntryDefinition::FEMALE], Rule::OPERATOR_NEQ],
            'EMPTY - true' => [true, null, [DictionaryEntryDefinition::MALE], Rule::OPERATOR_EMPTY],
            'EMPTY - false' => [false, $gender, [DictionaryEntryDefinition::MALE], Rule::OPERATOR_EMPTY],
        ];
    }
}
