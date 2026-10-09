<?php declare(strict_types=1);

namespace Shopwell\Core\Service\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\DynamicallyScheduledTaskHandler;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\Framework\Store\Services\FirstRunWizardService;
use Shopwell\Core\Service\LifecycleManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: InstallServicesTask::class)]
final class InstallServicesTaskHandler extends ScheduledTaskHandler implements DynamicallyScheduledTaskHandler
{
    private const FRW_PENDING_RETRY_INTERVAL = 'PT15M';

    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly LifecycleManager $manager,
        private readonly FirstRunWizardService $firstRunWizardService,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        if ($this->firstRunWizardService->frwShouldRun()) {
            return;
        }

        $this->manager->reconcile(Context::createCLIContext());
    }

    public function getNextExecutionTime(ScheduledTask $task, ScheduledTaskEntity $taskEntity): ?\DateTimeInterface
    {
        if (!$this->firstRunWizardService->frwShouldRun()) {
            return null;
        }

        return $this->now()->add(new \DateInterval(self::FRW_PENDING_RETRY_INTERVAL));
    }
}
