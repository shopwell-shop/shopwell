<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * @internal
 */
#[Package('framework')]
final class EndApprovalNodeCompiler implements ApprovalNodeCompiler
{
    public function getNodeType(): string
    {
        return DocumentNodeTypes::END;
    }

    public function getNodeVersion(): int
    {
        return 1;
    }

    public function getRole(): ApprovalNodeRole
    {
        return ApprovalNodeRole::END;
    }

    public function compile(ApprovalNodeCompilationContext $context): CompiledApprovalNode
    {
        $configuration = $context->rawConfiguration;

        if ($configuration instanceof \stdClass) {
            $configuration = get_object_vars($configuration);
        }

        if ($configuration !== []) {
            throw $context->reject('unsupported_end_configuration');
        }

        return new CompiledApprovalNode(WorkflowNodeTypes::END, outputPorts: []);
    }
}
