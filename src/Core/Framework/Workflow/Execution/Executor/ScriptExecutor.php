<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Evaluate a bounded expression with workflow data only; no PHP or service access is exposed.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ScriptExecutor implements NodeExecutorInterface
{
    public function __construct(private ExpressionLanguage $expressions = new ExpressionLanguage())
    {
    }

    public function getNodeType(): string
    {
        return WorkflowNodeTypes::SCRIPT;
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        $expression = $node->property('scriptExpression');
        if (!\is_string($expression) || trim($expression) === '') {
            throw WorkflowExecutionException::invalidGraph('Script nodes require an expression.');
        }

        try {
            $result = $this->expressions->evaluate($expression, [
                'form' => $instance->form(),
                'round' => $context->round(),
            ]);
        } catch (\Throwable $exception) {
            throw WorkflowExecutionException::invalidGraph('The workflow script failed: ' . $exception->getMessage());
        }

        if (!\is_scalar($result) && $result !== null && !\is_array($result)) {
            throw WorkflowExecutionException::invalidGraph('Workflow scripts may return only scalar, null or array values.');
        }

        return NodeOutcome::proceed(output: ['result' => $result]);
    }

    public function resume(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowSignal $signal, WorkflowExecutionContext $context): NodeOutcome
    {
        return $this->enter($instance, $node, $context);
    }
}
