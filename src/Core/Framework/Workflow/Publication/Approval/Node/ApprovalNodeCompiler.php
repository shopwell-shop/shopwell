<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\Log\Package;

/**
 * Register structured approval node compilation with shopwell.workflow.approval.node_compiler.
 * Keep each published version installed until its documents no longer need publication.
 */
#[Package('framework')]
interface ApprovalNodeCompiler
{
    public function getNodeType(): string;

    public function getNodeVersion(): int;

    public function getRole(): ApprovalNodeRole;

    public function compile(ApprovalNodeCompilationContext $context): CompiledApprovalNode;
}
