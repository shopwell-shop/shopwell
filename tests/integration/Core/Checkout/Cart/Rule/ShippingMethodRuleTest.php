<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\ShippingMethodRule;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleException;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ShippingMethodRuleTest extends TestCase
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

    public function testValidateWithMissingShippingMethodIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingMethodRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/shippingMethodIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
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
                'type' => (new ShippingMethodRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'shippingMethodIds' => [Uuid::randomHex(), Uuid::randomHex()],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
    }

    /**
     * @return iterable<array<string|bool|array<string, string|array<string>>>>
     */
    public static function matchDataProvider(): iterable
    {
        yield 'equals operator rejects when no shipping methods are configured' => [
            [
                'operator' => Rule::OPERATOR_EQ,
                'shippingMethodIds' => [],
            ],
            '965a0713093841ceb86b0f83edd7dab4',
            false,
        ];
        yield 'equals operator rejects a different shipping method' => [
            [
                'operator' => Rule::OPERATOR_EQ,
                'shippingMethodIds' => ['ff5a0713093841ceb86b0f83edd7dab4'],
            ],
            '965a0713093841ceb86b0f83edd7dab4',
            false,
        ];
        yield 'not equals operator rejects the configured shipping method' => [
            [
                'operator' => Rule::OPERATOR_NEQ,
                'shippingMethodIds' => ['965a0713093841ceb86b0f83edd7dab4'],
            ],
            '965a0713093841ceb86b0f83edd7dab4',
            false,
        ];
        yield 'not equals operator rejects one of multiple configured shipping methods' => [
            [
                'operator' => Rule::OPERATOR_NEQ,
                'shippingMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
            ],
            'ff5a0713093841ceb86b0f83edd7dab4',
            false,
        ];
        yield 'equals operator matches the configured shipping method' => [
            [
                'operator' => Rule::OPERATOR_EQ,
                'shippingMethodIds' => ['965a0713093841ceb86b0f83edd7dab4'],
            ],
            '965a0713093841ceb86b0f83edd7dab4',
            true,
        ];
        yield 'equals operator matches one of multiple configured shipping methods' => [
            [
                'operator' => Rule::OPERATOR_EQ,
                'shippingMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
            ],
            'ff5a0713093841ceb86b0f83edd7dab4',
            true,
        ];
        yield 'not equals operator matches an unconfigured shipping method' => [
            [
                'operator' => Rule::OPERATOR_NEQ,
                'shippingMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
            ],
            'ee5a0713093841ceb86b0f83edd7dab4',
            true,
        ];
    }

    /**
     * @param array<string, string|array<string>> $ruleProperties
     */
    #[DataProvider('matchDataProvider')]
    public function testMatch(array $ruleProperties, string $shippingMethodId, bool $expected): void
    {
        $shippingRule = new ShippingMethodRule();
        $shippingRule->assign($ruleProperties);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($shippingMethodId);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getShippingMethod')->willReturn($shippingMethod);

        $ruleScope = new CartRuleScope(
            new Cart('test'),
            $salesChannelContext
        );

        static::assertSame($expected, $shippingRule->match($ruleScope));
    }

    public function testExpectUnsupportedOperatorException(): void
    {
        $shippingMethodRule = new ShippingMethodRule();
        $shippingMethodRule->assign(['operator' => 'FOO', 'shippingMethodIds' => []]);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('965a0713093841ceb86b0f83edd7dab4');

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getShippingMethod')->willReturn($shippingMethod);

        $ruleScope = new CartRuleScope(
            new Cart('test'),
            $salesChannelContext
        );

        $this->expectExceptionObject(RuleException::unsupportedOperator('FOO', RuleComparison::class));

        $shippingMethodRule->match($ruleScope);
    }
}
