<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Release;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraft;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Stores immutable compilation records; activation requires separate runtime and business admission.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Release\ApprovalReleaseServiceTest
 */
#[Package('framework')]
class ApprovalReleaseService
{
    public function __construct(
        private readonly ApprovalReleaseStore $store,
        private readonly ApprovalReleaseCompiler $compiler,
    ) {
    }

    public function preview(ApprovalDraft $draft, int $expectedRevision): string
    {
        if ($draft->revision !== $expectedRevision) {
            throw ApprovalFlowException::draftRevisionConflict($draft->definitionId, $expectedRevision, $draft->revision);
        }

        return $this->compiler->compile($draft->graph)->hash;
    }

    public function publish(string $definitionId, int $expectedRevision, string $expectedHash, Context $context): PublishedApprovalRelease
    {
        return $this->store->publish($definitionId, $expectedRevision, $expectedHash, $context, fn (string $graphJson) => $this->compiler->compile(new DraftGraph($graphJson)));
    }

    public function read(string $releaseId, Context $context): ApprovalReleaseEntity
    {
        return $this->store->read($releaseId, $context);
    }
}
