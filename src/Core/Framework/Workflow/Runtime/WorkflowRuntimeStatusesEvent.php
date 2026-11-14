<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Runtime;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Runtimes contribute batch summaries for business-authorized release/instance bindings.
 * The caller must authorize the business records before dispatching; this event grants no detail access.
 * Unresolved references remain unavailable, never implicitly approved.
 */
#[Package('framework')]
final class WorkflowRuntimeStatusesEvent extends Event
{
    /**
     * @var array<string, array{status: WorkflowRuntimeStatus, currentAssignees: list<array{id: string, name: string}>}>
     */
    private array $statuses = [];

    /**
     * @param array<string, array{releaseId: string, publisher: string}> $references Keyed by bound instance ID.
     */
    public function __construct(public readonly array $references, public readonly Context $context)
    {
    }

    /**
     * @param list<array{id: string, name: string}> $currentAssignees
     */
    public function setStatus(string $instanceId, WorkflowRuntimeStatus $status, array $currentAssignees = []): void
    {
        if (!isset($this->references[$instanceId])) {
            throw WorkflowRuntimeException::invalidReference();
        }
        $this->statuses[$instanceId] = ['status' => $status, 'currentAssignees' => $currentAssignees];
    }

    /**
     * @return array<string, array{status: WorkflowRuntimeStatus, currentAssignees: list<array{id: string, name: string}>}>
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }
}
