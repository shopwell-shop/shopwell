<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\Framework\Webhook\Service\WebhookCleanup;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: CleanupWebhookEventLogTask::class)]
final class CleanupWebhookEventLogTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly WebhookCleanup $webhookCleanup
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->webhookCleanup->removeOldLogs();
    }
}
