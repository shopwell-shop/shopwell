<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\App\Event\SystemHeartbeatEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: SystemHeartbeatTask::class)]
final class SystemHeartbeatHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->eventDispatcher->dispatch(new SystemHeartbeatEvent());
    }
}
