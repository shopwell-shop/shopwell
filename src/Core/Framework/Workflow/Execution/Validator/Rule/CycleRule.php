<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;

/**
 * Detect cycles with three-color traversal so valid converging paths remain allowed.
 *
 * @internal
 */
#[Package('framework')]
final class CycleRule implements GraphRuleInterface
{
    private const WHITE = 0;
    private const GRAY = 1;
    private const BLACK = 2;

    public function validate(GraphData $graph, ValidationContext $context): array
    {
        /** @var array<string, int> $colors */
        $colors = [];
        /** @var list<string> $path */
        $path = [];
        $violations = [];
        $reported = [];

        $visit = function (string $nodeId) use (&$visit, &$colors, &$path, &$violations, &$reported, $graph): void {
            $colors[$nodeId] = self::GRAY;
            $path[] = $nodeId;

            foreach ($graph->outgoing($nodeId) as $edge) {
                $target = $edge->targetNodeId;

                if (!$graph->hasNode($target)) {
                    continue;   // ShapeRule reports missing edge endpoints.
                }

                $color = $colors[$target] ?? self::WHITE;

                if ($color === self::GRAY) {
                    // Extract the cycle from the target's first occurrence in the current path.
                    $start = array_search($target, $path, true);
                    $cycle = $start === false ? [$target] : \array_slice($path, (int) $start);
                    $cycle[] = $target;

                    // Deduplicate cycles by their node sets.
                    $key = implode('>', array_unique($cycle));

                    if (!isset($reported[$key])) {
                        $reported[$key] = true;
                        $violations[] = Violation::error(
                            'WORKFLOW_EXECUTION_004',
                            \sprintf('The workflow contains a cycle: %s', implode(' → ', $cycle)),
                            $target
                        );
                    }

                    continue;
                }

                if ($color === self::WHITE) {
                    $visit($target);
                }
            }

            array_pop($path);
            $colors[$nodeId] = self::BLACK;
        };

        foreach ($graph->nodeList() as $node) {
            if (($colors[$node->id] ?? self::WHITE) === self::WHITE) {
                $visit($node->id);
            }
        }

        return $violations;
    }
}
