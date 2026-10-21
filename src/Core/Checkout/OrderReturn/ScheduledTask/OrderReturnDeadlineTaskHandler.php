<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\ScheduledTask;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs the deadlines of the returns.
 *
 * A deadline never decides against the customer: an approval the shop did not decide on is either
 * approved automatically (when the merchant switched that on) or only remembered with a note. A
 * parcel that was never announced is different - the customer had the chance to send it and did not
 * use it, so the return is closed and the goods stay where they are.
 *
 * One broken return must not stop the others, which is why every row is handled on its own and a
 * failure is logged instead of thrown.
 *
 * @internal
 */
#[Package('after-sales')]
#[AsMessageHandler(handles: OrderReturnDeadlineTask::class)]
final class OrderReturnDeadlineTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     * @param EntityRepository<OrderReturnCollection> $orderReturnRepository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly EntityRepository $orderReturnRepository,
        private readonly OrderReturnService $orderReturnService,
        private readonly OrderReturnSettings $settings,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('deadlineAt', [RangeFilter::LTE => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        $criteria->addAssociation('stateMachineState');
        $criteria->setLimit(100);

        foreach ($this->orderReturnRepository->search($criteria, $context)->getEntities() as $orderReturn) {
            if (!$orderReturn instanceof OrderReturnEntity) {
                continue;
            }

            try {
                $this->handle($orderReturn, $context);
            } catch (\Throwable $exception) {
                $this->logger->error(
                    'The deadline of the return "{returnNumber}" could not be processed: {message}',
                    [
                        'returnNumber' => $orderReturn->returnNumber,
                        'message' => $exception->getMessage(),
                        'exception' => $exception,
                    ]
                );
            }
        }
    }

    private function handle(OrderReturnEntity $orderReturn, Context $context): void
    {
        $state = $orderReturn->stateMachineState?->getTechnicalName();
        $salesChannelId = $orderReturn->salesChannelId;

        if ($state === OrderReturnStates::STATE_REQUESTED) {
            if ($this->settings->autoApprove($salesChannelId)) {
                $this->orderReturnService->approve($orderReturn->id, $context);

                return;
            }

            // the shop did not decide in time and does not want an automatic approval: the deadline is
            // cleared so the row is not picked up every fifteen minutes, and the fact is written down
            $this->orderReturnService->addMessage(
                $orderReturn->id,
                'The decision of the shop is overdue.',
                OrderReturnEntity::AUTHOR_SYSTEM,
                null,
                null,
                $context
            );
            $this->clearDeadline($orderReturn->id, $context);

            return;
        }

        if ($state === OrderReturnStates::STATE_OPEN) {
            $this->orderReturnService->cancel($orderReturn->id, $context);
        }
    }

    private function clearDeadline(string $returnId, Context $context): void
    {
        $this->orderReturnRepository->update([[
            'id' => $returnId,
            'deadlineAt' => null,
        ]], $context);
    }
}
