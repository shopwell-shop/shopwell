<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Release;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class PublishedApprovalRelease
{
    public function __construct(
        public string $id,
        public string $definitionId,
        public int $draftRevision,
        public string $contentHash,
    ) {
    }
}
