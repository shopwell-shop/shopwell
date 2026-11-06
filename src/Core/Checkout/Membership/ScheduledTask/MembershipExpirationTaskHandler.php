<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Ends the memberships that ran out.
 *
 * The task only schedules and reports; what "ended" means - the row, the tier the customer falls back
 * to, the event that tier change dispatches - is the business of {@see MembershipSubscriptionService}.
 *
 * @internal
 */
#[Package('checkout')]
#[AsMessageHandler(handles: MembershipExpirationTask::class)]
final class MembershipExpirationTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly MembershipSubscriptionService $subscriptions,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->subscriptions->expireDue(Context::createCLIContext());
    }
}
