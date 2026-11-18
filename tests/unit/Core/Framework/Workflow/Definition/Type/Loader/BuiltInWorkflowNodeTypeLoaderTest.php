<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Definition\Type\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\BuiltInWorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BuiltInWorkflowNodeTypeLoader::class)]
class BuiltInWorkflowNodeTypeLoaderTest extends TestCase
{
    public function testProvidesOnlyImplementedBuiltInNodes(): void
    {
        $types = iterator_to_array((new BuiltInWorkflowNodeTypeLoader())->load(), false);

        static::assertCount(10, $types);
        static::assertSame('core:start', $types[0]->name);
        static::assertSame(WorkflowNodeKind::START, $types[0]->kind);
        static::assertSame([], $types[0]->inputPorts);
        static::assertSame(['out'], $types[0]->outputPorts);
        static::assertSame('core:end', $types[1]->name);
        static::assertSame(WorkflowNodeKind::END, $types[1]->kind);
        static::assertSame(['in'], $types[1]->inputPorts);
        static::assertSame([], $types[1]->outputPorts);
        static::assertSame('core:approval', $types[2]->name);
        static::assertSame(['approved', 'rejected'], $types[2]->outputPorts);
        static::assertSame('core:copy', $types[3]->name);
        static::assertSame(['in'], $types[3]->inputPorts);
        static::assertSame(['out'], $types[3]->outputPorts);
    }
}
