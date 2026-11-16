<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\GraphRuleInterface;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * Collect rule violations; wrap unexpected failures in a safe 500 while retaining the cause for logging.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowGraphValidator
{
    /**
     * @param iterable<GraphRuleInterface> $rules
     */
    public function __construct(private readonly iterable $rules)
    {
    }

    /**
     * @return list<Violation>
     */
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];

        foreach ($this->rules as $rule) {
            try {
                foreach ($rule->validate($graph, $context) as $violation) {
                    $violations[] = $violation;
                }
            } catch (\Throwable $exception) {
                throw WorkflowExecutionException::validationFailed($exception);
            }
        }

        return $violations;
    }

    /**
     * Errors prevent saving or publishing; warnings do not.
     *
     * @param list<Violation> $violations
     */
    public function hasErrors(array $violations): bool
    {
        foreach ($violations as $violation) {
            if ($violation->isError()) {
                return true;
            }
        }

        return false;
    }
}
