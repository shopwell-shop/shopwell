<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\ScheduledTask;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('checkout')]
#[AsMessageHandler(handles: LoyaltyPointExpirationTask::class)]
final class LoyaltyPointExpirationTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<CustomerPointTransactionCollection> $pointTransactionRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly EntityRepository $pointTransactionRepository,
        private readonly LoyaltyPointService $loyaltyPointService,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $now = $this->clock->now();

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('type', LoyaltyPointService::TRANSACTION_TYPE_AWARD),
            new RangeFilter('expiresAt', [RangeFilter::LTE => $now->format(\DATE_ATOM)]),
            new EqualsFilter('expiredAt', null),
        );
        $criteria->setLimit(1000);

        $transactions = $this->pointTransactionRepository->search($criteria, $context)->getEntities();

        if ($transactions->count() === 0) {
            return;
        }

        $expiringPoints = [];
        $updates = [];

        foreach ($transactions as $transaction) {
            if (!$transaction instanceof CustomerPointTransactionEntity) {
                continue;
            }

            $customerId = $transaction->getCustomerId();
            $expiringPoints[$customerId] = ($expiringPoints[$customerId] ?? 0) + $transaction->getPoints();

            $updates[] = [
                'id' => $transaction->getId(),
                'expiredAt' => $now,
            ];
        }

        // mark the expired awards first so a re-run cannot book them twice
        $this->pointTransactionRepository->update($updates, $context);

        foreach ($expiringPoints as $customerId => $points) {
            // points may already have been redeemed, so the expired amount is capped by the current balance
            $balance = $this->loyaltyPointService->getBalance($customerId, $context);

            $this->loyaltyPointService->expire($customerId, min($points, $balance), $context);
        }
    }
}
