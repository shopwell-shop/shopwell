<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: ExpirePaymentOrdersTask::class)]
final class ExpirePaymentOrdersTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly PaymentOrderService $orderService,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->orderService->expireDue();
    }
}
