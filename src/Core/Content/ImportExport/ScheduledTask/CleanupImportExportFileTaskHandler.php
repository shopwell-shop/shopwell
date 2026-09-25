<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[AsMessageHandler(handles: CleanupImportExportFileTask::class)]
final class CleanupImportExportFileTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly DeleteExpiredFilesService $deleteExpiredFilesService
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->deleteExpiredFilesService->deleteFiles(Context::createCLIContext());
    }
}
