<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopwell\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopwell\Core\Checkout\Cart\Rule\GoodsCountRule;
use Shopwell\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemGoodsTotalRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemGroupRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemWrapperRule;
use Shopwell\Core\Checkout\Customer\Rule\BillingZipCodeRule;
use Shopwell\Core\Checkout\Customer\Rule\CustomerCustomFieldRule;
use Shopwell\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\AndRule;
use Shopwell\Core\Framework\Rule\Container\Container;
use Shopwell\Core\Framework\Rule\Container\FilterRule;
use Shopwell\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopwell\Core\Framework\Rule\Container\NotRule;
use Shopwell\Core\Framework\Rule\Container\OrRule;
use Shopwell\Core\Framework\Rule\Container\XorRule;
use Shopwell\Core\Framework\Rule\Container\ZipCodeRule;
use Shopwell\Core\Framework\Rule\DateRangeRule;
use Shopwell\Core\Framework\Rule\Rule as ShopwellRule;
use Shopwell\Core\Framework\Rule\ScriptRule;
use Shopwell\Core\Framework\Rule\SimpleRule;
use Shopwell\Core\Framework\Rule\TimeRangeRule;
use Shopwell\Core\Test\Stub\Rule\FalseRule;
use Shopwell\Core\Test\Stub\Rule\TrueRule;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class RuleConditionHasRuleConfigRule implements Rule
{
    /**
     * @var list<string>
     */
    private array $rulesAllowedToBeWithoutConfig = [
        ZipCodeRule::class,
        FilterRule::class,
        Container::class,
        AndRule::class,
        NotRule::class,
        OrRule::class,
        XorRule::class,
        MatchAllLineItemsRule::class,
        ScriptRule::class,
        DateRangeRule::class,
        SimpleRule::class,
        TimeRangeRule::class,
        GoodsCountRule::class,
        GoodsPriceRule::class,
        LineItemRule::class,
        LineItemWithQuantityRule::class,
        LineItemWrapperRule::class,
        BillingZipCodeRule::class,
        ShippingZipCodeRule::class,
        AlwaysValidRule::class,
        LineItemCustomFieldRule::class,
        LineItemGoodsTotalRule::class,
        CustomerCustomFieldRule::class,
        LineItemGroupRule::class,
        FalseRule::class,
        TrueRule::class,
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isRuleClass($scope) || $this->isAllowed($scope) || $this->isValid($scope)) {
            if ($this->isAllowed($scope) && $this->isValid($scope)) {
                return [
                    RuleErrorBuilder::message('This class is implementing the getConfig function and has a own admin component. Remove getConfig or the component.')
                        ->identifier('shopwell.ruleConfig')
                        ->build(),
                ];
            }

            return [];
        }

        return [
            RuleErrorBuilder::message('This class has to implement getConfig or implement a new admin component.')
                ->identifier('shopwell.ruleConfig')
                ->build(),
        ];
    }

    private function isValid(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null || !$class->hasMethod('getConfig')) {
            return false;
        }

        $declaringClass = $class->getMethod('getConfig', $scope)->getDeclaringClass();

        return $declaringClass->getName() !== ShopwellRule::class;
    }

    private function isAllowed(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }

        return \in_array($class->getName(), $this->rulesAllowedToBeWithoutConfig, true);
    }

    private function isRuleClass(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }

        $namespace = $class->getName();
        if (!\str_contains($namespace, 'Shopwell\\Tests\\Unit\\') && !\str_contains($namespace, 'Shopwell\\Tests\\Migration\\')) {
            return false;
        }

        return $class->is(ShopwellRule::class);
    }
}
