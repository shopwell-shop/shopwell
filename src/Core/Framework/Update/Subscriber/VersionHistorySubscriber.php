<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Update\Subscriber;

use Shopwell\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopwell\Core\Framework\Update\Services\VersionHistory;
use Shopwell\Core\Framework\Update\Services\VersionHistoryEntryType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class VersionHistorySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VersionHistory $versionHistory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemInstallCompletedEvent::class => 'onInstallCompleted',
            UpdatePostFinishEvent::class => 'onUpdateFinished',
        ];
    }

    public function onInstallCompleted(): void
    {
        $this->versionHistory->record(VersionHistoryEntryType::INSTALL);
    }

    public function onUpdateFinished(): void
    {
        $this->versionHistory->record(VersionHistoryEntryType::UPDATE);
    }
}
