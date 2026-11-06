<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Starts the nightly expiry.
 *
 * Nothing but the hand-over lives here: which points have run out, which customer they belong to and
 * what has to happen to their balance is {@see LoyaltyPointService::expireDue()}'s business. The
 * previous version kept the scan, the marking and the debiting in this class, which is how the two
 * writes ended up in two transactions.
 *
 * @internal
 */
#[Package('checkout')]
#[AsMessageHandler(handles: LoyaltyPointExpirationTask::class)]
final class LoyaltyPointExpirationTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly LoyaltyPointService $loyaltyPointService,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->loyaltyPointService->expireDue(Context::createCLIContext());
    }
}
