<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Version\Cleanup;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Version\CleanupVersionTaskHandlerTest
 */
#[Package('framework')]
#[AsMessageHandler(handles: CleanupVersionTask::class)]
final class CleanupVersionTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly int $days,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $time = $this->clock->now()->modify(\sprintf('-%d day', $this->days));
        $cleanupEvent = new CleanupVersionEvent($time);
        $this->eventDispatcher->dispatch($cleanupEvent);
        $protectedVersionIds = $this->getProtectedVersionIds($cleanupEvent);

        do {
            $query = 'DELETE FROM version WHERE created_at <= :timestamp';
            $parameters = ['timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
            $types = [];

            if ($protectedVersionIds !== []) {
                $query .= ' AND id NOT IN (:protectedVersionIds)';
                $parameters['protectedVersionIds'] = $protectedVersionIds;
                $types['protectedVersionIds'] = ArrayParameterType::BINARY;
            }

            $result = $this->connection->executeStatement($query . ' LIMIT 1000', $parameters, $types);
        } while ($result > 0);

        do {
            $query = 'DELETE FROM version_commit WHERE created_at <= :timestamp';
            $parameters = ['timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
            $types = [];

            if ($protectedVersionIds !== []) {
                $query .= ' AND version_id NOT IN (:protectedVersionIds)';
                $parameters['protectedVersionIds'] = $protectedVersionIds;
                $types['protectedVersionIds'] = ArrayParameterType::BINARY;
            }

            $result = $this->connection->executeStatement($query . ' LIMIT 1000', $parameters, $types);
        } while ($result > 0);
    }

    /**
     * @return list<string>
     */
    private function getProtectedVersionIds(CleanupVersionEvent $event): array
    {
        $protectedVersionIds = [];

        foreach ($event->getProtectedVersionIds() as $versionId) {
            if (!Uuid::isValid($versionId)) {
                continue;
            }

            $protectedVersionIds[$versionId] = Uuid::fromHexToBytes($versionId);
        }

        return array_values($protectedVersionIds);
    }
}
