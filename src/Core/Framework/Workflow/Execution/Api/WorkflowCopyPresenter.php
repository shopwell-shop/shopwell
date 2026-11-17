<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\WorkflowCopyApiTest
 */
#[Package('framework')]
final readonly class WorkflowCopyPresenter
{
    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instances
     */
    public function __construct(private EntityRepository $instances, private WorkflowUserLookup $users)
    {
    }

    /**
     * Only project instances reached through the caller's authorized receipt collection.
     *
     * @return list<array<string, mixed>>
     */
    public function presentMany(WorkflowCopyCollection $copies, Context $context): array
    {
        if ($copies->count() === 0) {
            return [];
        }

        $instanceIds = array_values(array_unique($copies->map(static fn ($copy): string => $copy->instanceId)));
        $instances = $this->instances->search(new Criteria($instanceIds), $context)->getEntities();
        $initiatorIds = array_values($instances->map(static fn (WorkflowInstanceEntity $instance): string => $instance->initiatorId));
        $names = $this->users->names($initiatorIds, $context);
        $rows = [];

        foreach ($copies as $copy) {
            $instance = $instances->get($copy->instanceId);

            if ($instance === null) {
                continue;
            }

            $graph = GraphData::fromArray($instance->snapshot());
            $rows[] = [
                'id' => $copy->id,
                'instanceId' => $copy->instanceId,
                'nodeId' => $copy->nodeId,
                'round' => $copy->round,
                'nodeName' => $graph->node($copy->nodeId)->name(),
                'title' => $instance->title,
                'number' => $instance->number,
                'initiator' => ['id' => $instance->initiatorId, 'name' => $names[$instance->initiatorId] ?? $instance->initiatorId],
                'createdAt' => $copy->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'readAt' => $copy->readAt?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }
}
