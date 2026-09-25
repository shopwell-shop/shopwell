<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Rule;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\TimeRangeRule;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class TimeRangeRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $ruleRepository = static::getContainer()->get('rule.repository');
        /** @var EntityRepository<RuleConditionCollection> $conditionRepository */
        $conditionRepository = static::getContainer()->get('rule_condition.repository');

        $ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $context
        );

        $id = Uuid::randomHex();
        $conditionRepository->create([
            [
                'id' => $id,
                'type' => (new TimeRangeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'fromTime' => '15:00',
                    'toTime' => '12:00',
                    'timezone' => 'Europe/Berlin',
                ],
            ],
        ], $context);

        $result = $conditionRepository->search(new Criteria([$id]), $context)
            ->getEntities()
            ->get($id);

        static::assertInstanceOf(RuleConditionEntity::class, $result);
        $value = $result->getValue();
        static::assertIsArray($value);
        static::assertArrayHasKey('toTime', $value);
        static::assertArrayHasKey('fromTime', $value);
        static::assertSame('12:00', $value['toTime']);
        static::assertSame('15:00', $value['fromTime']);
        static::assertSame('Europe/Berlin', $value['timezone']);

        $ruleRepository->delete([['id' => $ruleId]], $context);
        $conditionRepository->delete([['id' => $id]], $context);
    }
}
