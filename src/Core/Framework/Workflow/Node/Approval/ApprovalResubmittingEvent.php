<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * Validate normalized replacement inputs before restarting a returned instance.
 * Throwing aborts the transaction; the saved graph, initiator and organization metadata remain unchanged.
 */
#[Package('framework')]
final readonly class ApprovalResubmittingEvent
{
    /**
     * @param array<string, mixed> $formData
     */
    public function __construct(
        public WorkflowInstanceEntity $instance,
        public array $formData,
        public Context $context,
    ) {
    }
}
