<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ShippingZipCodeRuleTest extends TestCase
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

    public function testValidateWithMissingZipCodes(): void
    {
        // reset from previous tests
        static::getContainer()->get(ShippingZipCodeRule::class)->assign(['operator' => Rule::OPERATOR_EQ, 'zipCodes' => null]);

        $conditionId = Uuid::randomHex();

        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new ShippingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithEmptyZipCodes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'zipCodes' => [],
                        'operator' => ShippingZipCodeRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithStringZipCodes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'zipCodes' => '12345',
                        'operator' => ShippingZipCodeRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);
            static::assertSame('FRAMEWORK__WRITE_CONSTRAINT_VIOLATION', $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidArrayZipCodes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'zipCodes' => [true, 3.1, null, '12345'],
                        'operator' => ShippingZipCodeRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/zipCodes', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/zipCodes', $exceptions[2]['source']['pointer']);

            static::assertSame('This value "1" should be of type string.', $exceptions[0]['detail']);
            static::assertSame('This value "3.1" should be of type string.', $exceptions[1]['detail']);
            static::assertSame('This value "" should be of type string.', $exceptions[2]['detail']);
        }
    }

    public function testValidateEmptyOperatorWithoutZipCodes(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingZipCodeRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'zipCodes' => ['12345'],
                        'operator' => ShippingZipCodeRule::OPERATOR_EMPTY,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);
            static::assertSame('The property "zipCodes" is not allowed.', $exceptions[0]['detail']);
        }

        $id = Uuid::randomHex();
        // should not throw an exception
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new ShippingZipCodeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => ShippingZipCodeRule::OPERATOR_EMPTY,
                ],
            ],
        ], $this->context);

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new ShippingZipCodeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'zipCodes' => ['12345', '54321'],
                    'operator' => ShippingZipCodeRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }

    public function testValidateWithInvalidGreaterThanCondition(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'zipCodes' => 12345,
                        'operator' => ShippingZipCodeRule::OPERATOR_GT,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);

            static::assertSame('This value should be of type array.', $exceptions[0]['detail']);
        }
    }

    public function testValidateWithValidGreaterThanCondition(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new ShippingZipCodeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'zipCodes' => ['12345'],
                    'operator' => ShippingZipCodeRule::OPERATOR_GT,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }
}
