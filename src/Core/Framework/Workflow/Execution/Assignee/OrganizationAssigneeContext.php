<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowInstanceMetadataEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * Freezes department identity; staff and their eligibility remain live at node entry.
 */
#[Package('framework')]
final readonly class OrganizationAssigneeContext implements EventSubscriberInterface
{
    private const METADATA_KEY = 'core:organization';

    public function __construct(private OrganizationAssigneeLookup $organizations)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowInstanceMetadataEvent::class => 'capture'];
    }

    public function capture(WorkflowInstanceMetadataEvent $event): void
    {
        $membership = null;
        if ($event->initiatorId !== null) {
            $membership = $this->organizations->primaryMembership($event->initiatorId, $event->context);
        }

        $event->addMetadata(self::METADATA_KEY, ['departmentId' => $membership?->getOrganizationUnitId()]);
    }

    public function departmentId(WorkflowInstanceEntity $instance): ?string
    {
        $organization = $instance->ext[self::METADATA_KEY] ?? null;
        if ($organization === null) {
            return null;
        }

        if (!\is_array($organization) || !\array_key_exists('departmentId', $organization)) {
            throw WorkflowExecutionException::invalidGraph('The instance organization snapshot is invalid.');
        }

        $departmentId = $organization['departmentId'];
        if ($departmentId !== null && (!\is_string($departmentId) || !Uuid::isValid($departmentId))) {
            throw WorkflowExecutionException::invalidGraph('The instance department snapshot is invalid.');
        }

        return $departmentId;
    }
}
