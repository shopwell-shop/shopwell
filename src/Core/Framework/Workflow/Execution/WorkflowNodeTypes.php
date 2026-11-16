<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * Node types in the compiled execution graph consumed by the executor registry.
 * This internal representation differs from the designer workflow document.
 * Publishers compile designer nodes into these execution types and properties.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowNodeTypes
{
    /**
     * The unique start node.
     */
    public const START = 'snaker:start';

    /**
     * A task node whose properties select assignees and the approval mode.
     */
    public const TASK = 'snaker:task';

    /**
     * A copy node records recipients without creating tasks or blocking execution.
     */
    public const COPY = 'snaker:copy';

    /**
     * The unique end node.
     */
    public const END = 'snaker:end';

    /**
     * Routes through conditional outgoing edges without creating tasks.
     */
    public const CONDITION = 'snaker:condition';

    /**
     * Node types accepted by the execution contract.
     */
    public const ALL = [self::START, self::TASK, self::COPY, self::END, self::CONDITION];

    /**
     * Reserved subprocess type; register an executor when implementing it.
     */
    public const FUTURE_SUBPROCESS = 'snaker:subprocess';
}
