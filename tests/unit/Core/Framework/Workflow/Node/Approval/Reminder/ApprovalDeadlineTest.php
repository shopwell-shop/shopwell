<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Reminder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalDeadline;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalDeadline::class)]
class ApprovalDeadlineTest extends TestCase
{
    public function testNormalizesAndCalculatesElapsedTimeAcrossDaylightSaving(): void
    {
        $deadline = ApprovalDeadline::fromConfiguration(['minutes' => 120, 'remind' => true]);
        static::assertSame(['minutes' => 120, 'remind' => true], $deadline->configuration());
        $created = new \DateTimeImmutable('2026-11-01T01:30:00-04:00');
        static::assertSame('2026-11-01T07:30:00+00:00', $deadline->dueAt($created)->format(\DateTimeInterface::ATOM));
    }

    public function testWorkingTimeSkipsTheWeekendAndCountsTheConfiguredBusinessWindow(): void
    {
        $deadline = ApprovalDeadline::fromConfiguration(['minutes' => 120, 'remind' => true, 'workingTime' => true]);
        static::assertSame(['minutes' => 120, 'remind' => true, 'workingTime' => true], $deadline->configuration());
        $created = new \DateTimeImmutable('2026-11-06T17:00:00+00:00');
        static::assertSame('2026-11-09T10:00:00+00:00', $deadline->dueAt($created)->format(\DateTimeInterface::ATOM));
    }

    public function testPreservesAnEscalationTarget(): void
    {
        $escalateTo = '0123456789abcdef0123456789abcdef';
        $deadline = ApprovalDeadline::fromConfiguration([
            'minutes' => 120,
            'remind' => true,
            'workingTime' => true,
            'escalateTo' => $escalateTo,
        ]);

        static::assertSame([
            'minutes' => 120,
            'remind' => true,
            'workingTime' => true,
            'escalateTo' => $escalateTo,
        ], $deadline->configuration());
        static::assertSame($escalateTo, $deadline->escalateTo);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'zero duration' => [['minutes' => 0, 'remind' => true]];
        yield 'fractional duration' => [['minutes' => 1.5, 'remind' => true]];
        yield 'string duration' => [['minutes' => '60', 'remind' => true]];
        yield 'duration above one year' => [['minutes' => 525601, 'remind' => true]];
        yield 'missing reminder choice' => [['minutes' => 60]];
        yield 'non-boolean reminder' => [['minutes' => 60, 'remind' => 1]];
        yield 'unknown field' => [['minutes' => 60, 'remind' => true, 'action' => 'approve']];
        yield 'invalid escalation target' => [['minutes' => 60, 'remind' => true, 'escalateTo' => 'admin']];
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsMalformedConfiguration(mixed $configuration): void
    {
        $this->expectException(WorkflowExecutionException::class);
        ApprovalDeadline::fromConfiguration($configuration);
    }
}
