<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\ApprovalExecutionCommandContext;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalExecutionCommandContext::class)]
class ApprovalExecutionCommandContextTest extends TestCase
{
    public function testNodeCommandsRetainTheirTargetAndOriginalContext(): void
    {
        $context = Context::createDefaultContext();
        $command = new ApprovalExecutionCommandContext('instance', 'release', 'command', $context, 'execution');

        static::assertSame('execution', $command->requireExecutionId());
        static::assertSame($context, $command->context);
    }

    public function testAnInstanceCommandDoesNotInventANodeExecutionTarget(): void
    {
        $command = new ApprovalExecutionCommandContext('instance', 'release', 'command', Context::createDefaultContext());
        $this->expectExceptionObject(ApprovalFlowException::invalidInstanceCommand('This command requires a node execution target.'));

        $command->requireExecutionId();
    }
}
