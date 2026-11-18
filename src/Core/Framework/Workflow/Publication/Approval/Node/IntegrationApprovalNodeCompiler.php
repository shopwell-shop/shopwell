<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Compile connector, data-action, notification and pinned subprocess activities.
 * Delivery is delegated through WorkflowAutomationActionEvent so connectors remain replaceable.
 *
 * @internal
 */
#[Package('framework')]
final class IntegrationApprovalNodeCompiler implements ApprovalNodeCompiler
{
    public function __construct(private readonly string $designerType, private readonly string $runtimeType, private readonly TranslatorInterface $translator)
    {
    }

    public function getNodeType(): string
    {
        return $this->designerType;
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
        $configuration = $context->configuration(['action', 'label', 'description', 'input', 'releaseId', 'event']);
        $action = $configuration['action'] ?? $configuration['event'] ?? null;
        if (!\is_string($action) || preg_match('/^[a-z][a-z0-9_.:-]{0,127}$/D', $action) !== 1) {
            throw $context->reject('invalid_integration_action', '/action');
        }
        $input = $configuration['input'] ?? [];
        if (!\is_array($input) || ($input !== [] && array_is_list($input))) {
            throw $context->reject('invalid_integration_input', '/input');
        }
        if ($this->designerType === DocumentNodeTypes::SUBPROCESS && (!\is_string($configuration['releaseId'] ?? null) || $configuration['releaseId'] === '')) {
            throw $context->reject('invalid_subprocess_release', '/releaseId');
        }

        return new CompiledApprovalNode($this->runtimeType, [
            'name' => $configuration['label'] ?? $this->translator->trans('workflow.nodes.' . basename(str_replace(':', '/', $this->designerType))),
            'description' => $configuration['description'] ?? '',
            'automationAction' => $action,
            'automationInput' => $input,
            'subprocessReleaseId' => $configuration['releaseId'] ?? null,
        ]);
    }
}
