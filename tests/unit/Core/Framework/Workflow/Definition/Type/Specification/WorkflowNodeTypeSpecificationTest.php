<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Definition\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowNodeTypeSpecification::class)]
class WorkflowNodeTypeSpecificationTest extends TestCase
{
    public function testCustomTypeCarriesControlPortsWithoutBusinessSemantics(): void
    {
        $type = new WorkflowNodeTypeSpecification('example:task', 2, WorkflowNodeKind::ACTIVITY, ['in'], ['done', 'failed']);

        static::assertSame('example:task', $type->name);
        static::assertSame(2, $type->version);
        static::assertSame(['done', 'failed'], $type->outputPorts);
    }

    /**
     * @param list<string> $inputs
     * @param list<string> $outputs
     */
    #[DataProvider('invalidTypes')]
    public function testRejectsInvalidTypeDefinitions(string $name, int $version, WorkflowNodeKind $kind, array $inputs, array $outputs): void
    {
        $this->expectExceptionObject(WorkflowException::invalidNodeType($name));

        new WorkflowNodeTypeSpecification($name, $version, $kind, $inputs, $outputs);
    }

    public static function invalidTypes(): \Generator
    {
        yield 'type needs a namespace' => ['task', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']];
        yield 'version must be positive' => ['example:task', 0, WorkflowNodeKind::ACTIVITY, ['in'], ['out']];
        yield 'ports cannot be duplicated' => ['example:task', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out', 'out']];
        yield 'port cannot be empty' => ['example:task', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['']];
        yield 'start cannot accept incoming control' => ['example:start', 1, WorkflowNodeKind::START, ['in'], ['out']];
        yield 'end cannot emit outgoing control' => ['example:end', 1, WorkflowNodeKind::END, ['in'], ['out']];
        yield 'activity needs an input' => ['example:task', 1, WorkflowNodeKind::ACTIVITY, [], ['out']];
        yield 'activity needs an output' => ['example:task', 1, WorkflowNodeKind::ACTIVITY, ['in'], []];
    }
}
