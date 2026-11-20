<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingCollection;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;

/**
 * Delivers committed results at least once; each business owns its transaction and deduplication.
 *
 * @internal
 */
#[Package('framework')]
class ApprovalResultDispatcher
{
    /**
     * @param EntityRepository<ApprovalRequestCollection> $requests
     * @param EntityRepository<ApprovalBindingCollection> $bindings
     */
    public function __construct(
        private readonly ApprovalResultOutbox $outbox,
        private readonly EntityRepository $requests,
        private readonly EntityRepository $bindings,
        private readonly ApprovalResultHandlerRegistry $handlers,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatchPending(int $limit = 100): int
    {
        $delivered = 0;
        $context = Context::createCLIContext();
        for ($index = 0; $index < $limit; ++$index) {
            $delivery = $this->outbox->claim($context);
            if ($delivery === null) {
                break;
            }

            try {
                $criteria = (new Criteria())->addFilter(new EqualsFilter('instanceId', $delivery->result->instanceId));
                $request = $this->requests->search($criteria, $context)->getEntities()->first();
                if ($request === null) {
                    throw ApprovalFlowException::resultRequestNotFound($delivery->result->instanceId);
                }
                $binding = $this->bindings->search(new Criteria([$request->getBindingId()]), $context)->getEntities()->first();
                if ($binding === null) {
                    throw ApprovalFlowException::resultRequestNotFound($delivery->result->instanceId);
                }
                $handler = $this->handlers->get($request->getBusinessType(), $binding->getBusinessVersion());
                $handler->handle($delivery->result, $request, $context);
                if ($this->outbox->acknowledge($delivery->result->id, $delivery->attempt, $context)) {
                    ++$delivered;
                }
            } catch (\Throwable $exception) {
                $this->outbox->retry($delivery->result->id, $delivery->attempt, $context);
                $this->logger->error('Approval result delivery failed.', ['resultId' => $delivery->result->id, 'instanceId' => $delivery->result->instanceId, 'exception' => $exception]);
            }
        }

        return $delivered;
    }
}
