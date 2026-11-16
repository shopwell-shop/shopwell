<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class TaskApprovalNodeCompiler implements ApprovalNodeCompiler
{
    public function __construct(
        private readonly ApprovalConfigurationCompiler $approvals,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getNodeType(): string
    {
        return DocumentNodeTypes::APPROVAL;
    }

    public function getNodeVersion(): int
    {
        return 1;
    }

    public function getRole(): ApprovalNodeRole
    {
        return ApprovalNodeRole::APPROVAL;
    }

    public function compile(ApprovalNodeCompilationContext $context): CompiledApprovalNode
    {
        $configuration = $context->configuration(['assignee', 'approval', 'label', 'description']);
        $compiled = $this->approvals->compile($configuration, $context->path, $context->nodeId);
        $name = $configuration['label'] ?? '';

        if (!\is_string($name) || trim($name) === '') {
            $name = $this->translator->trans('workflow.nodes.approval');
        }

        return new CompiledApprovalNode(
            WorkflowNodeTypes::TASK,
            [
                'approvalStage' => true,
                'name' => $name,
                'description' => $configuration['description'] ?? '',
                'assigneeType' => $compiled->assignment->type,
                'assigneeValue' => $compiled->assignment->values,
                'assigneeConfig' => $compiled->assignment->configuration,
                'assigneeUserIds' => $compiled->assignment->knownUserIds,
                'countersignType' => $compiled->mode->getType(),
                'approvalModeVersion' => $compiled->mode->getVersion(),
                'approvalModeConfig' => $compiled->configuration,
            ],
            outputPorts: ['approved' => []],
            terminalPorts: ['rejected'],
        );
    }
}
