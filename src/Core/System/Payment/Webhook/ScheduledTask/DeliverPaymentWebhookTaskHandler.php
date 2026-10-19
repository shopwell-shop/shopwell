<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Webhook\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: DeliverPaymentWebhookTask::class)]
final class DeliverPaymentWebhookTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly PaymentWebhookDispatcher $webhookService,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->webhookService->retryDue();
    }
}
