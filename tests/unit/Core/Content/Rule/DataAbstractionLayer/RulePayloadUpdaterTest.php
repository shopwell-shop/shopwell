<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Rule\DataAbstractionLayer\RulePayloadUpdater;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopwell\Core\Framework\Rule\Container\AndRule;
use Shopwell\Core\Framework\Rule\DateRangeRule;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RulePayloadUpdater::class)]
class RulePayloadUpdaterTest extends TestCase
{
    public function testUpdate(): void
    {
        $connection = $this->createMock(Connection::class);
        $registry = $this->createMock(RuleConditionRegistry::class);
        $clock = new MockClock('2026-01-13 12:00:00');

        $ruleId = Uuid::randomHex();
        $expectedPayload = serialize(new AndRule([new DateRangeRule()]));
        $conditions = [
            [
                'array_key' => $ruleId,
                'id' => 'root',
                'parent_id' => null,
                'type' => DateRangeRule::RULE_NAME,
                'value' => null,
            ],
        ];

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($conditions);

        $statement = $this->createMock(Statement::class);
        $params = [
            ['id', Uuid::fromHexToBytes($ruleId)],
            ['payload', $expectedPayload],
            ['invalid', 0],
            ['updatedAt', $clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
        ];

        $matcher = $this->exactly(\count($params));

        $statement->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(static function (string $key, $value) use ($matcher, $params): void {
                $expected = $params[$matcher->numberOfInvocations() - 1];
                self::assertSame($expected[0], $key);
                self::assertSame($expected[1], $value);
            });

        $statement->expects($this->once())->method('executeStatement')->willReturn(1);

        $connection->expects($this->once())
            ->method('prepare')
            ->with('UPDATE `rule` SET payload = :payload, invalid = :invalid, updated_at = :updatedAt WHERE id = :id')
            ->willReturn($statement);

        $registry->expects($this->once())
            ->method('has')
            ->with(DateRangeRule::RULE_NAME)
            ->willReturn(true);
        $registry->expects($this->once())
            ->method('getRuleClass')
            ->with(DateRangeRule::RULE_NAME)
            ->willReturn(DateRangeRule::class);

        $updater = new RulePayloadUpdater($connection, $registry, $clock);

        $updater->update([$ruleId]);
    }
}
