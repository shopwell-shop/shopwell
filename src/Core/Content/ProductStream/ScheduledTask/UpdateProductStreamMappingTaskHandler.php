<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopwell\Core\Content\ProductStream\ProductStreamCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: UpdateProductStreamMappingTask::class)]
final class UpdateProductStreamMappingTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     * @param EntityRepository<ProductStreamCollection> $productStreamRepository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly EntityRepository $productStreamRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('filters.type', 'until'),
            new EqualsFilter('filters.type', 'since'),
        ]));

        $streamIds = $this->productStreamRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($streamIds === []) {
            return;
        }

        // Touch the streams so cache invalidation subscribers (e.g. stream HTTP cache tags) fire.
        // ProductStreamUpdater::update() skips re-indexing when no filter property changed, so the
        // mapping update has to be triggered explicitly below.
        $this->productStreamRepository->update($streamIds, $context);

        foreach ($streamIds as $streamId) {
            $message = new ProductStreamMappingIndexingMessage($streamId['id']);
            $message->setIndexer(ProductStreamUpdater::INDEXER_NAME);
            $this->messageBus->dispatch($message);
        }
    }
}
