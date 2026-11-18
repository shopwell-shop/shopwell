<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeOutcome::class)]
class NodeOutcomeTest extends TestCase
{
    public function testCompletionSeparatesTechnicalCompletionFromBusinessResult(): void
    {
        $outcome = NodeOutcome::complete('payment-declined', ['reason' => 'missing-assignee']);

        static::assertTrue($outcome->isComplete());
        static::assertSame('payment-declined', $outcome->result);
        static::assertSame(['reason' => 'missing-assignee'], $outcome->output);
        static::assertFalse($outcome->isWaiting());
    }

    public function testContinuationCarriesAnExplicitPortAndNodeOutput(): void
    {
        $outcome = NodeOutcome::proceed('accepted', ['reference' => 'bank-123']);

        static::assertTrue($outcome->isContinue());
        static::assertSame('accepted', $outcome->port);
        static::assertSame(['reference' => 'bank-123'], $outcome->output);
    }

    public function testWaitingDeclaresTheSignalThatCanResumeTheNode(): void
    {
        $outcome = NodeOutcome::wait('bank:response');

        static::assertTrue($outcome->isWaiting());
        static::assertSame('bank:response', $outcome->waitingFor);
    }
}
