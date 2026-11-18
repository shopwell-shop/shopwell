<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskActions;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskPolicy;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\MissingApprovalAssigneePolicy;
use Shopwell\Core\Framework\Workflow\Node\Approval\Automation\ApprovalAutomation;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormPolicy;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalDeadline;
use Shopwell\Core\Framework\Workflow\WorkflowException;
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
        private readonly ApprovalTaskPolicy $operations,
        private readonly ApprovalFormPolicy $forms = new ApprovalFormPolicy(),
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
        $configuration = $context->configuration(['assignee', 'approval', 'label', 'description', 'operations', 'deadline', 'formPermissions', 'missingAssignee', 'automation', 'taskKind']);
        $taskKind = $configuration['taskKind'] ?? 'approval';
        if (!\in_array($taskKind, ['approval', 'handling'], true)) {
            throw $context->reject('approval_invalid_configuration', '/taskKind');
        }
        try {
            $formPermissions = $this->forms->normalize(\array_key_exists('formPermissions', $configuration) ? $configuration['formPermissions'] : [], $context->inputSchema);
        } catch (WorkflowExecutionException) {
            throw $context->reject('approval_invalid_form_permissions', '/formPermissions');
        }
        try {
            $automation = ApprovalAutomation::fromConfiguration(\array_key_exists('automation', $configuration) ? $configuration['automation'] : []);
        } catch (WorkflowExecutionException) {
            throw $context->reject('approval_invalid_configuration', '/automation');
        }
        $compiled = $this->approvals->compile($configuration, $context->path, $context->nodeId);
        try {
            $missingAssignee = MissingApprovalAssigneePolicy::fromConfiguration(\array_key_exists('missingAssignee', $configuration) ? $configuration['missingAssignee'] : []);
        } catch (WorkflowException) {
            throw $context->reject('approval_invalid_configuration', '/missingAssignee');
        }
        try {
            $operations = $this->operations->normalize(\array_key_exists('operations', $configuration) ? $configuration['operations'] : ApprovalTaskActions::OPERATIONS);
        } catch (WorkflowExecutionException) {
            throw $context->reject('approval_invalid_operations', '/operations');
        }
        $deadline = [];
        if (\array_key_exists('deadline', $configuration)) {
            try {
                $deadline = ['approvalDeadline' => ApprovalDeadline::fromConfiguration($configuration['deadline'])->configuration()];
            } catch (WorkflowExecutionException) {
                throw $context->reject('approval_invalid_deadline', '/deadline');
            }
        }

        $knownUserIds = $compiled->assignment->knownUserIds;
        if ($missingAssignee->userId !== null) {
            $knownUserIds[] = $missingAssignee->userId;
        }

        $name = $configuration['label'] ?? '';

        if (!\is_string($name) || trim($name) === '') {
            $name = $this->translator->trans('workflow.nodes.approval');
        }

        return new CompiledApprovalNode(
            WorkflowNodeTypes::TASK,
            [
                ...$deadline,
                'approvalStage' => true,
                'handlingTask' => $taskKind === 'handling',
                'approvalAutomation' => $automation->configuration(),
                'approvalFormPermissions' => $formPermissions,
                'approvalOperations' => $operations,
                'name' => $name,
                'description' => $configuration['description'] ?? '',
                'assigneeType' => $compiled->assignment->type,
                'assigneeValue' => $compiled->assignment->values,
                'assigneeConfig' => $compiled->assignment->configuration,
                'assigneeUserIds' => array_values(array_unique($knownUserIds)),
                'missingAssignee' => $missingAssignee->configuration(),
                'countersignType' => $compiled->mode->getType(),
                'approvalModeVersion' => $compiled->mode->getVersion(),
                'approvalModeConfig' => $compiled->configuration,
            ],
            outputPorts: ['approved' => []],
            terminalPorts: ['rejected'],
        );
    }
}
