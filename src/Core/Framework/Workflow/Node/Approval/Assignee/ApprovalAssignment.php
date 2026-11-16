<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;

/**
 * Compiled assignment; known user IDs are checked at publication, dynamic sources resolve on node entry.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalAssignment
{
    /**
     * @param list<string> $values
     * @param array<string, mixed> $configuration
     * @param list<string> $knownUserIds
     */
    public function __construct(
        public string $type,
        public array $values,
        public bool $multiple,
        public array $configuration = [],
        public array $knownUserIds = [],
    ) {
    }
}
