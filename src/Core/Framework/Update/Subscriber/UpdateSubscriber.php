<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Update\Subscriber;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Notification\NotificationService;
use Shopwell\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class UpdateSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => [
                ['updateFinishedDone', -9999],
            ],
        ];
    }

    /**
     * @internal
     */
    public function updateFinishedDone(UpdatePostFinishEvent $event): void
    {
        if ($event->getPostUpdateMessage() === '') {
            return;
        }

        $source = $event->getContext()->getSource();
        $integrationId = null;
        $createdByUserId = null;
        if ($source instanceof AdminApiSource) {
            $integrationId = $source->getIntegrationId();
            $createdByUserId = $source->getUserId();
        }

        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'warning',
                'message' => $event->getPostUpdateMessage(),
                'adminOnly' => true,
                'requiredPrivileges' => [],
                'createdByIntegrationId' => $integrationId,
                'createdByUserId' => $createdByUserId,
            ],
            $event->getContext()
        );
    }
}
