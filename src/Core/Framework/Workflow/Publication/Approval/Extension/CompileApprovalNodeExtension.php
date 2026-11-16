<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\CompiledApprovalNode;

/**
 * Select a registered compiler of the same structural role before publication.
 * Result replacement and short-circuiting are rejected; results must come from that compiler.
 * Changes affect new publications; persisted graphs are never recompiled on execution.
 *
 * @extends Extension<CompiledApprovalNode>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CompileApprovalNodeExtension extends Extension
{
    public const NAME = 'workflow.approval.compile-node';

    /**
     * @internal
     */
    public function __construct(
        public ApprovalNodeCompiler $compiler,
        public readonly ApprovalNodeCompilationContext $context,
    ) {
    }
}
