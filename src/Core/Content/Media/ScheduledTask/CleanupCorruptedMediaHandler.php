<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\ScheduledTask;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('discovery')]
#[AsMessageHandler(handles: CleanupCorruptedMediaTask::class)]
final class CleanupCorruptedMediaHandler extends ScheduledTaskHandler
{
    private const CORRUPTED_MEDIA_GRACE_PERIOD_DAYS = 30;

    private const CORRUPTED_MEDIA_BATCH_SIZE = 500;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected readonly LoggerInterface $logger,
        private readonly EntityRepository $mediaRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $lastId = null;

        while (true) {
            $criteria = $this->buildCleanupCriteria($lastId);
            $criteria->setLimit(self::CORRUPTED_MEDIA_BATCH_SIZE);

            $ids = $this->mediaRepository->searchIds($criteria, $context)->getPrimaryKeyData();
            if ($ids === []) {
                return;
            }

            $lastId = array_last($ids)['id'];

            $this->mediaRepository->delete($ids, $context);
        }
    }

    private function buildCleanupCriteria(?string $lastId = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $criteria->addFilter(new EqualsFilter('uploadedAt', null));
        $criteria->addFilter(new EqualsFilter('path', null));
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::LT => $this->clock->now()
                ->sub(new \DateInterval('P' . self::CORRUPTED_MEDIA_GRACE_PERIOD_DAYS . 'D'))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        if ($lastId !== null) {
            $criteria->addFilter(new RangeFilter('id', [RangeFilter::GT => Uuid::fromHexToBytes($lastId)]));
        }

        return $criteria;
    }
}
