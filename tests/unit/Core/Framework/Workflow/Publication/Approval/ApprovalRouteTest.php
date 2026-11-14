<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ConditionEvaluatorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalRoute::class)]
class ApprovalRouteTest extends TestCase
{
    public function testFirstMatchingConditionWinsEvenWhenDefaultIsListedFirst(): void
    {
        $conditions = static::createStub(ConditionEvaluatorInterface::class);
        $conditions->method('evaluate')->willReturn(true);
        $route = new ApprovalRoute($conditions);
        static::assertSame(['nodeIds' => ['start', 'first', 'end'], 'edgeIds' => ['first-branch', 'first-end']], $route->path($this->graph(), []));
        static::assertSame(['nodeIds' => ['start', 'first'], 'edgeIds' => ['first-branch']], $route->path($this->graph(), [], 'first'));
    }

    public function testDefaultSelectedWhenNoConditionMatches(): void
    {
        $conditions = static::createStub(ConditionEvaluatorInterface::class);
        $conditions->method('evaluate')->willReturn(false);
        static::assertSame(['nodeIds' => ['start', 'end'], 'edgeIds' => ['default']], (new ApprovalRoute($conditions))->path($this->graph(), []));
    }

    private function graph(): GraphData
    {
        return GraphData::fromArray(['nodes' => [
            ['id' => 'start', 'type' => 'snaker:start'], ['id' => 'first', 'type' => 'snaker:task'],
            ['id' => 'second', 'type' => 'snaker:task'], ['id' => 'end', 'type' => 'snaker:end'],
        ], 'edges' => [
            ['id' => 'default', 'sourceNodeId' => 'start', 'targetNodeId' => 'end'],
            ['id' => 'first-branch', 'sourceNodeId' => 'start', 'targetNodeId' => 'first', 'properties' => ['expr' => 'first']],
            ['id' => 'second-branch', 'sourceNodeId' => 'start', 'targetNodeId' => 'second', 'properties' => ['expr' => 'second']],
            ['id' => 'first-end', 'sourceNodeId' => 'first', 'targetNodeId' => 'end'],
            ['id' => 'second-end', 'sourceNodeId' => 'second', 'targetNodeId' => 'end'],
        ]]);
    }
}
