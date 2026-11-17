<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormPolicy;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class CopyApprovalNodeCompiler implements ApprovalNodeCompiler
{
    public function __construct(private TranslatorInterface $translator, private ApprovalFormPolicy $forms = new ApprovalFormPolicy())
    {
    }

    public function getNodeType(): string
    {
        return DocumentNodeTypes::COPY;
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
        $configuration = $context->configuration(['label', 'description', 'userIds', 'formPermissions']);
        try {
            $formPermissions = $this->forms->normalize(\array_key_exists('formPermissions', $configuration) ? $configuration['formPermissions'] : [], $context->inputSchema, allowEdit: false);
        } catch (WorkflowExecutionException) {
            throw $context->reject('approval_invalid_form_permissions', '/formPermissions');
        }
        $recipients = $configuration['userIds'] ?? null;

        if (!\is_array($recipients) || !array_is_list($recipients) || $recipients === []) {
            throw $context->reject('invalid_copy_recipients', '/userIds');
        }

        $userIds = [];

        foreach ($recipients as $index => $recipient) {
            if (!\is_string($recipient) || !Uuid::isValid(strtolower($recipient))) {
                throw $context->reject('invalid_copy_recipients', '/userIds/' . $index);
            }

            $userId = strtolower($recipient);

            if (\in_array($userId, $userIds, true)) {
                throw $context->reject('invalid_copy_recipients', '/userIds/' . $index);
            }

            $userIds[] = $userId;
        }

        $name = trim($configuration['label'] ?? '');

        return new CompiledApprovalNode(WorkflowNodeTypes::COPY, [
            'name' => $name !== '' ? $configuration['label'] : $this->translator->trans('workflow.nodes.copy'),
            'description' => $configuration['description'] ?? '',
            'userIdList' => $userIds,
            'approvalFormPermissions' => $formPermissions,
        ]);
    }
}
