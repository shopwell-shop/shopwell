<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\Stats;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\Stats\Entity\MessageStatsResponseEntity;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('framework')]
class StatsService
{
    public function __construct(
        private readonly AbstractStatsRepository $statsRepository,
        private readonly bool $enabled,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getStats(): MessageStatsResponseEntity
    {
        if (!$this->enabled) {
            return new MessageStatsResponseEntity(enabled: false);
        }

        return new MessageStatsResponseEntity(
            enabled: true,
            stats: $this->statsRepository->getStats()
        );
    }

    public function registerMessage(Envelope $envelope): void
    {
        if (!$this->enabled) {
            return;
        }

        $sentAtStamp = $envelope->last(SentAtStamp::class);
        if ($sentAtStamp === null) {
            return;
        }

        $timeInQueue = $this->clock->now()->getTimestamp() - $sentAtStamp->getSentAt()->getTimestamp();
        $messageFqcn = $envelope->getMessage()::class;
        $this->statsRepository->updateMessageStats($messageFqcn, $timeInQueue);
    }
}
