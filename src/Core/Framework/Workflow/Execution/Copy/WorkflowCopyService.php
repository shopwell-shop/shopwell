<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Copy;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\WorkflowCopyApiTest
 */
#[Package('framework')]
final readonly class WorkflowCopyService
{
    /**
     * @param EntityRepository<WorkflowCopyCollection> $copies
     */
    public function __construct(
        private EntityRepository $copies,
        private WorkflowService $workflows,
        private WorkflowTransactionRunner $transactions,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return EntitySearchResult<WorkflowCopyCollection>
     */
    public function received(Criteria $criteria, Context $context): EntitySearchResult
    {
        $criteria->addFilter(new EqualsFilter('userId', $this->workflows->actorId($context)));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING), new FieldSorting('id'));

        return $this->copies->search($criteria, $context);
    }

    public function markRead(string $id, Context $context): void
    {
        $actorId = $this->workflows->actorId($context);
        $receipt = $this->ownedReceipt($id, $actorId, $context);

        $this->transactions->run(function () use ($receipt, $actorId, $context): void {
            $this->transactions->lockInstance($receipt->instanceId);
            $fresh = $this->ownedReceipt($receipt->id, $actorId, $context);

            if ($fresh->readAt !== null) {
                return;
            }

            $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($fresh): void {
                $this->copies->update([['id' => $fresh->id, 'readAt' => $this->clock->now()]], $scoped);
            });
        });
    }

    private function ownedReceipt(string $id, string $actorId, Context $context): WorkflowCopyEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('userId', $actorId));
        $receipt = $this->copies->search($criteria, $context)->getEntities()->first();

        return $receipt ?? throw WorkflowExecutionException::forbidden('Only the copy recipient can acknowledge this receipt.');
    }
}
