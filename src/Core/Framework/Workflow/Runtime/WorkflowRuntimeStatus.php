<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Runtime;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WorkflowRuntimeStatus
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELED = 'canceled';

    /**
     * @param 'pending'|'approved'|'rejected'|'canceled' $status
     * @param array{name: string, params: array<string, string>}|null $detailRoute
     */
    public function __construct(
        public string $status,
        public ?array $detailRoute = null,
        public bool $canCancel = false,
    ) {
    }
}
