<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * Evaluate conditions using the syntax supported by the registered implementation.
 *
 * @internal Experimental execution extension point; not covered by the backward compatibility promise.
 */
#[Package('framework')]
interface ConditionEvaluatorInterface
{
    /**
     * Evaluate a contract expression against form data; malformed expressions must throw rather than select a default branch.
     *
     * @param array<string, mixed> $formData
     *
     * @throws WorkflowExecutionException
     */
    public function evaluate(string $expr, array $formData): bool;
}
