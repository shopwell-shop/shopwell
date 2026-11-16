<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\CompiledApprovalNode;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompiledApprovalNode::class)]
class CompiledApprovalNodeTest extends TestCase
{
    public function testPreservesOrderedPortsAndRuntimeProperties(): void
    {
        $node = new CompiledApprovalNode('example:route', ['name' => 'Route'], outputPorts: ['second' => [], 'first' => []]);

        static::assertSame(['second', 'first'], array_keys($node->outputPorts));
        static::assertSame(['name' => 'Route'], $node->properties);
    }

    public function testOutputCannotBeBothRoutedAndTerminal(): void
    {
        $this->expectExceptionObject(WorkflowException::invalidDocument('/compilation/ports', 'Ports must be unique names.'));

        new CompiledApprovalNode('example:route', terminalPorts: ['out']);
    }

    public function testRejectsInvalidPortNames(): void
    {
        $this->expectExceptionObject(WorkflowException::invalidDocument('/compilation/ports', 'Port names are invalid.'));

        new CompiledApprovalNode('example:route', inputPorts: ['invalid port']);
    }
}
