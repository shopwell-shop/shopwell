<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Validator\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ShapeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShapeRule::class)]
class ShapeRuleTest extends TestCase
{
    #[DataProvider('identityLengths')]
    public function testRuntimeIdentitiesFitPersistedActivations(int $length, bool $accepted): void
    {
        $nodeId = str_repeat('n', $length);
        $edgeId = str_repeat('e', $length);
        $graph = GraphData::fromArray(['nodes' => [
            ['id' => $nodeId, 'type' => 'snaker:start'],
            ['id' => 'end', 'type' => 'snaker:end'],
        ], 'edges' => [['id' => $edgeId, 'sourceNodeId' => $nodeId, 'targetNodeId' => 'end']]]);

        $violations = (new ShapeRule())->validate($graph, new ValidationContext());

        if ($accepted) {
            static::assertSame([], $violations);

            return;
        }

        static::assertCount(2, $violations);
        static::assertContains($nodeId, array_column($violations, 'nodeId'));
        static::assertContains($edgeId, array_column($violations, 'edgeId'));
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function identityLengths(): iterable
    {
        yield 'storage maximum' => [64, true];
        yield 'first overflowing identity' => [65, false];
        yield 'editor maximum still cannot execute' => [128, false];
        yield 'beyond editor maximum cannot execute' => [129, false];
    }
}
