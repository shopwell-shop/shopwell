<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class ParallelApprovalNodeCompiler implements ApprovalNodeCompiler
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
        $configuration = $context->configuration(['label', 'description', 'branchCount']);
        $count = $configuration['branchCount'] ?? 2;
        if (!\is_int($count) || $count < 2 || $count > 20) {
            throw $context->reject('invalid_parallel_branch_count', '/branchCount');
        }
        if ($this->designerType === DocumentNodeTypes::JOIN) {
            $inputs = array_map(static fn (int $index): string => 'branch-' . $index, range(0, $count - 1));

            return new CompiledApprovalNode(
                $this->runtimeType,
                ['name' => $configuration['label'] ?? $this->translator->trans('workflow.nodes.join')],
                inputPorts: $inputs,
                outputPorts: ['out' => []],
            );
        }
        $ports = [];
        for ($index = 0; $index < $count; ++$index) {
            $ports['branch-' . $index] = [];
        }

        return new CompiledApprovalNode($this->runtimeType, ['name' => $configuration['label'] ?? $this->translator->trans('workflow.nodes.parallel'), 'branchCount' => $count], outputPorts: $ports);
    }
}
