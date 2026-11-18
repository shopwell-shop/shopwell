<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Compile a deliberately small, expression based script node.
 *
 * @internal
 */
#[Package('framework')]
final class ScriptApprovalNodeCompiler implements ApprovalNodeCompiler
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getNodeType(): string
    {
        return DocumentNodeTypes::SCRIPT;
    }

    public function getNodeVersion(): int
    {
        return 1;
    }

    public function getRole(): ApprovalNodeRole
    {
        return ApprovalNodeRole::ACTIVITY;
    }

    public function compile(ApprovalNodeCompilationContext $context): CompiledApprovalNode
    {
        $configuration = $context->configuration(['expression', 'label', 'description']);
        $expression = $configuration['expression'] ?? null;
        if (!\is_string($expression) || trim($expression) === '' || mb_strlen($expression) > 10000) {
            throw $context->reject('invalid_script', '/expression');
        }

        return new CompiledApprovalNode(
            WorkflowNodeTypes::SCRIPT,
            [
                'name' => $configuration['label'] ?? $this->translator->trans('workflow.nodes.script'),
                'description' => $configuration['description'] ?? '',
                'scriptExpression' => trim($expression),
            ],
        );
    }
}
