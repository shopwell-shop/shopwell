<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Cleanup;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 *  @internal
 */
#[Package('checkout')]
#[AsMessageHandler(handles: CleanupCartTask::class)]
final class CleanupCartTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly AbstractCartPersister $cartPersister,
        private readonly int $days
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->cartPersister->prune($this->days);
    }
}
