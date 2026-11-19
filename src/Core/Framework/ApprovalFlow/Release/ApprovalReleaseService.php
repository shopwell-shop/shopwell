<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Release;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftDefinition;
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

    public function publish(string $definitionId, int $expectedRevision, string $expectedHash, Context $context): PublishedApprovalRelease
    {
        $this->requirePrivileges($context, ApprovalDraftDefinition::ENTITY_NAME . ':read', ApprovalReleaseDefinition::ENTITY_NAME . ':create');

        return $this->store->publish($definitionId, $expectedRevision, $expectedHash, $context, fn (string $graphJson) => $this->compiler->compile(new DraftGraph($graphJson)));
    }

    public function read(string $releaseId, Context $context): ApprovalReleaseEntity
    {
        $this->requirePrivileges($context, ApprovalReleaseDefinition::ENTITY_NAME . ':read');

        return $this->store->read($releaseId, $context);
    }

    private function requirePrivileges(Context $context, string ...$privileges): void
    {
        $source = $context->getSource();
        if ($source instanceof SystemSource) {
            return;
        }
        $missing = array_values(array_filter(
            $privileges,
            static fn (string $privilege): bool => !$source instanceof AdminApiSource || !$source->isAllowed($privilege),
        ));
        if ($missing !== []) {
            throw ApprovalFlowException::missingPrivileges($missing);
        }
    }
}
