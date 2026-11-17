<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class ConditionApprovalNodeCompiler implements ApprovalNodeCompiler
{
    public function __construct(
        private readonly ApprovalCondition $conditions,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getNodeType(): string
    {
        return DocumentNodeTypes::CONDITION;
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
        $configuration = $context->configuration(['branches', 'label', 'description']);
        $ports = $this->conditionPorts($configuration, $context->inputSchema, $context);

        return new CompiledApprovalNode(
            WorkflowNodeTypes::CONDITION,
            ['name' => $configuration['label'] ?? $this->translator->trans('workflow.nodes.condition')],
            outputPorts: $ports,
        );
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<array<string, mixed>> $schema
     *
     * @return array<string, array<string, mixed>>
     */
    private function conditionPorts(array $config, array $schema, ApprovalNodeCompilationContext $context): array
    {
        $branches = $config['branches'] ?? null;

        if (!\is_array($branches) || !array_is_list($branches) || $branches === [] || \count($branches) > 50) {
            throw $context->reject('invalid_branches', '/branches');
        }

        $ports = [];

        foreach ($branches as $index => $raw) {
            $branch = $raw instanceof \stdClass ? get_object_vars($raw) : $raw;

            if (!\is_array($branch) || ($branch !== [] && array_is_list($branch)) || array_diff(array_keys($branch), ['id', 'label', 'field', 'fieldType', 'operator', 'value', 'conditions']) !== []) {
                throw $context->reject('invalid_branch_configuration', '/branches/' . $index);
            }

            if (!\is_string($branch['id'] ?? null) || preg_match('/^branch-[a-zA-Z0-9-]+$/D', $branch['id']) !== 1 || isset($ports[$branch['id']])) {
                throw $context->reject('invalid_branch_id', '/branches/' . $index . '/id');
            }

            if (!\is_string($branch['label'] ?? null) || trim($branch['label']) === '' || mb_strlen($branch['label']) > 255) {
                throw $context->reject('invalid_branch_label', '/branches/' . $index . '/label');
            }

            try {
                $expression = $this->conditions->encode($branch, $schema);
            } catch (WorkflowExecutionException) {
                throw $context->reject('invalid_branch_condition', '/branches/' . $index);
            }

            $ports[$branch['id']] = ['expr' => $expression, 'name' => $branch['label']];
        }

        $ports['fallback'] = ['name' => $this->translator->trans('workflow.nodes.fallback')];

        return $ports;
    }
}
