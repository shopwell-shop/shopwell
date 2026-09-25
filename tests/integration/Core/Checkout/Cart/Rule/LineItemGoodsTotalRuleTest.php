<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemGoodsTotalRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\AndRule;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Checkout\CartRuleFixture;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[Group('rules')]
class LineItemGoodsTotalRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

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

    public function testValidateWithMissingParameters(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemGoodsTotalRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithStringCount(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemGoodsTotalRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'count' => '3',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithFloatCount(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemGoodsTotalRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'count' => 1.1,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testAvailableOperators(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $conditionIdEq = Uuid::randomHex();
        $conditionIdNEq = Uuid::randomHex();
        $conditionIdLTE = Uuid::randomHex();
        $conditionIdGTE = Uuid::randomHex();
        $this->conditionRepository->create(
            [
                [
                    'id' => $conditionIdEq,
                    'type' => (new LineItemGoodsTotalRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'count' => 1,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new LineItemGoodsTotalRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'count' => 1,
                        'operator' => Rule::OPERATOR_NEQ,
                    ],
                ],
                [
                    'id' => $conditionIdLTE,
                    'type' => (new LineItemGoodsTotalRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'count' => 1,
                        'operator' => Rule::OPERATOR_LTE,
                    ],
                ],
                [
                    'id' => $conditionIdGTE,
                    'type' => (new LineItemGoodsTotalRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'count' => 1,
                        'operator' => Rule::OPERATOR_GTE,
                    ],
                ],
            ],
            $this->context
        );

        static::assertCount(
            4,
            $this->conditionRepository->search(
                new Criteria([$conditionIdEq, $conditionIdNEq, $conditionIdLTE, $conditionIdGTE]),
                $this->context
            )->getEntities()
        );
    }

    public function testValidateWithInvalidOperator(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemGoodsTotalRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'count' => 42,
                        'operator' => 'Invalid',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exceptions[0]['code']);
        }
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
                'type' => (new LineItemGoodsTotalRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'count' => 1,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
    }

    public function testCreateRuleWithFilter(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [
                [
                    'id' => $ruleId,
                    'name' => 'LineItemRule',
                    'priority' => 0,
                    'conditions' => [
                        [
                            'type' => (new LineItemGoodsTotalRule())->getName(),
                            'ruleId' => $ruleId,
                            'children' => [
                                [
                                    'type' => (new LineItemOfTypeRule())->getName(),
                                    'value' => [
                                        'lineItemType' => 'test',
                                        'operator' => Rule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                            'value' => [
                                'count' => 100,
                                'operator' => Rule::OPERATOR_GTE,
                            ],
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), Context::createDefaultContext())->getEntities()->get($ruleId);

        static::assertNotNull($rule);
        static::assertFalse($rule->isInvalid());
        static::assertInstanceOf(AndRule::class, $rule->getPayload());
        /** @var AndRule $andRule */
        $andRule = $rule->getPayload();
        static::assertInstanceOf(LineItemGoodsTotalRule::class, $andRule->getRules()[0]);
        $filterRule = (new \ReflectionProperty(LineItemGoodsTotalRule::class, 'filter'))->getValue($andRule->getRules()[0]);
        static::assertInstanceOf(AndRule::class, $filterRule);
        static::assertInstanceOf(LineItemOfTypeRule::class, $filterRule->getRules()[0]);
    }

    public function testRuleMatchesWithOneLineItemMoreQuantity(): void
    {
        $rule = new LineItemGoodsTotalRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_GTE]);

        $lineItemCollection = new LineItemCollection([
            CartRuleFixture::createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 3),
        ]);
        $cart = CartRuleFixture::createCart($lineItemCollection);

        static::assertTrue($rule->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class))));
    }
}
