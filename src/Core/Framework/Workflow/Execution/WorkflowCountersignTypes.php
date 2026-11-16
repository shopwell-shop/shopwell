<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;

/**
 * Approval modes; CounterSignCalculator owns their decision semantics.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowCountersignTypes
{
    /**
     * One assignee decides the node's outcome.
     */
    public const SINGLE = 'single';

    /**
     * Any approval passes; rejection requires every assignee to reject.
     */
    public const OR_SIGN = 'orSign';

    /**
     * The first approval or rejection completes the node.
     */
    public const FIRST_DECISION = 'firstDecision';

    /**
     * Every assignee must approve; any rejection rejects the node.
     */
    public const COUNTERSIGN = 'countersign';

    /**
     * Assignees approve in order; the final approval passes the node.
     */
    public const ORDER_SIGN = 'orderSign';

    public const ALL = [self::SINGLE, self::OR_SIGN, self::FIRST_DECISION, self::COUNTERSIGN, self::ORDER_SIGN];

    /**
     * Definitions without a mode default to single approval.
     */
    public const DEFAULT = self::SINGLE;

    /**
     * Read the approval mode and reject unknown values instead of silently changing semantics.
     */
    public static function fromNode(GraphNode $node): string
    {
        $type = $node->property('countersignType');

        if ($type === null || $type === '') {
            return self::DEFAULT;
        }

        if (!\is_string($type) || !\in_array($type, self::ALL, true)) {
            throw WorkflowExecutionException::unsupportedCountersignType(\is_string($type) ? $type : get_debug_type($type));
        }

        return $type;
    }

    /**
     * Whether the mode can create multiple tasks; this does not determine the outcome.
     */
    public static function isMultiPerson(string $type): bool
    {
        return $type !== self::SINGLE;
    }
}
