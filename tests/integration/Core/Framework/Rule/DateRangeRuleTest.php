<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Rule;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\DateRangeRule;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class DateRangeRuleTest extends TestCase
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

    public function testValidateWithoutParameters(): void
    {
        $conditionId = Uuid::randomHex();

        $exception = new WriteException();
        $exception->add(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    'This value should not be blank.',
                    'This value should not be blank.',
                    ['{{ value }}' => 'null'],
                    null,
                    '/value/fromDate',
                    null,
                    null,
                    NotBlank::IS_BLANK_ERROR,
                ),
                new ConstraintViolation(
                    'This value should not be blank.',
                    'This value should not be blank.',
                    ['{{ value }}' => 'null'],
                    null,
                    '/value/toDate',
                    null,
                    null,
                    NotBlank::IS_BLANK_ERROR,
                ),
                new ConstraintViolation(
                    'This value should not be null.',
                    'This value should not be null.',
                    ['{{ value }}' => 'null'],
                    null,
                    '/value/useTime',
                    null,
                    null,
                    NotNull::IS_NULL_ERROR,
                ),
            ]),
            '/0',
        ));
        $this->expectExceptionObject($exception);

        $this->conditionRepository->create([
            [
                'id' => $conditionId,
                'type' => (new DateRangeRule())->getName(),
                'ruleId' => Uuid::randomHex(),
            ],
        ], $this->context);
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
                'type' => (new DateRangeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'toDate' => '2018-12-06T10:03:35',
                    'fromDate' => '2018-12-06T10:03:35',
                    'useTime' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }
}
