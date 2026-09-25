<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\InAppPurchase\Handler;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\Framework\Store\InAppPurchase\InAppPurchaseUpdateTask;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
#[AsMessageHandler(handles: InAppPurchaseUpdateTask::class)]
final class InAppPurchaseUpdateHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly InAppPurchaseUpdater $iapUpdater
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $this->iapUpdater->update($context);
    }
}
