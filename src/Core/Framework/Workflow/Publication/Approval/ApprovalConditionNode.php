<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Shopwell\Core\Framework\Log\Package;

/**
 * A normalized comparison or boolean group, constructed by the condition codec.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalConditionNode
{
    /**
     * @param list<self> $conditions
     * @param array<string, mixed>|null $field Null identifies a boolean group.
     */
    public function __construct(
        public string $operator,
        public array $conditions = [],
        public ?array $field = null,
        public string $value = '',
    ) {
    }
}
