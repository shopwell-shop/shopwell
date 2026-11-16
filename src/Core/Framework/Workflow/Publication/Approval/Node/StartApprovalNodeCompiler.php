<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class StartApprovalNodeCompiler implements ApprovalNodeCompiler
{
    public function __construct(
        private readonly WorkflowInputSchema $forms,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getNodeType(): string
    {
        return DocumentNodeTypes::START;
    }

    public function getNodeVersion(): int
    {
        return 1;
    }

    public function getRole(): ApprovalNodeRole
    {
        return ApprovalNodeRole::START;
    }

    public function compile(ApprovalNodeCompilationContext $context): CompiledApprovalNode
    {
        $config = $context->configuration(['inputType', 'formFields', 'label', 'description', 'requestAction']);

        if (\array_key_exists('inputType', $config) && $config['inputType'] !== 'core:approval-request') {
            throw $context->reject('unsupported_input_type', '/inputType');
        }

        if (isset($config['requestAction'])
            && (!\is_string($config['requestAction']) || preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-z0-9_-]*$/D', $config['requestAction']) !== 1)) {
            throw $context->reject('invalid_request_action', '/requestAction');
        }

        $fields = $config['formFields'] ?? null;

        if (!\array_key_exists('formFields', $config)) {
            $fields = [[
                'key' => 'reason',
                'label' => $this->translator->trans('workflow.inputs.reason'),
                'type' => 'text',
            ]];
        }

        try {
            $schema = $this->forms->normalize($fields);
        } catch (WorkflowExecutionException $exception) {
            // Input diagnostics use /fields; locate that field within the designer's start configuration.
            $fieldPath = $exception->getParameters()['path'];
            throw $context->reject('invalid_input_schema', '/formFields' . substr($fieldPath, \strlen('/fields')));
        }

        return new CompiledApprovalNode(WorkflowNodeTypes::START, ['inputSchema' => $schema], inputPorts: []);
    }
}
