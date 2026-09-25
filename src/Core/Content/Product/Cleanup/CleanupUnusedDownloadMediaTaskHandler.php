<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Cleanup;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Media\UnusedMediaPurger;
use Shopwell\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: CleanupUnusedDownloadMediaTask::class)]
final class CleanupUnusedDownloadMediaTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly UnusedMediaPurger $unusedMediaPurger
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->unusedMediaPurger->deleteNotUsedMedia(
            null,
            null,
            null,
            ProductDownloadDefinition::ENTITY_NAME
        );
    }
}
