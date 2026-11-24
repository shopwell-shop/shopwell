<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Cleanup;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Media\UnusedMediaPurger;
use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Files uploaded in the last day stay, because their document may not be saved yet.
 *
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: CleanupUnusedProductDocumentMediaTask::class)]
final class CleanupUnusedProductDocumentMediaTaskHandler extends ScheduledTaskHandler
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
            gracePeriodDays: 1,
            folderEntity: ProductDocumentDefinition::ENTITY_NAME,
        );
    }
}
