<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Notification;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type Notification array{id: non-empty-string, status: string, message: string, adminOnly?: bool, requiredPrivileges: array<int, string>, createdByIntegrationId?: string|null, createdByUserId?: string|null}
 */
#[Package('framework')]
class NotificationService
{
    /**
     * @param EntityRepository<NotificationCollection> $notificationRepository
     */
    public function __construct(private readonly EntityRepository $notificationRepository)
    {
    }

    /**
     * @param Notification $data
     */
    public function createNotification(array $data, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->notificationRepository->create([$data], $context);
        });
    }

    /**
     * @return array{notifications: NotificationCollection, timestamp: string|null}
     */
    public function getNotifications(Context $context, int $limit, ?string $latestTimestamp): array
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw NotificationException::invalidAdminSource($context->getSource()::class);
        }

        $criteria = new Criteria();
        $isAdmin = $source->isAdmin();
        if (!$isAdmin) {
            $criteria->addFilter(new EqualsFilter('adminOnly', false));
        }

        if ($latestTimestamp) {
            $criteria->addFilter(new RangeFilter('createdAt', [
                RangeFilter::GT => $latestTimestamp,
            ]));
        }

        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);

        $notifications = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->notificationRepository->search($criteria, $context)->getEntities();
        });

        if ($notifications->count() === 0) {
            return [
                'notifications' => new NotificationCollection(),
                'timestamp' => null,
            ];
        }

        $latestTimestamp = $notifications->last()?->getCreatedAt()?->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if ($isAdmin) {
            return [
                'notifications' => $notifications,
                'timestamp' => $latestTimestamp,
            ];
        }

        $notifications = $this->formatNotifications($notifications, $source);

        return [
            'notifications' => $notifications,
            'timestamp' => $latestTimestamp,
        ];
    }

    private function formatNotifications(NotificationCollection $notifications, AdminApiSource $source): NotificationCollection
    {
        $responseNotifications = new NotificationCollection();

        /** @var NotificationEntity $notification */
        foreach ($notifications as $notification) {
            if ($this->isAllow($notification->getRequiredPrivileges(), $source)) {
                $responseNotifications->add($notification);
            }
        }

        return $responseNotifications;
    }

    /**
     * @param array<string> $privileges
     */
    private function isAllow(array $privileges, AdminApiSource $source): bool
    {
        foreach ($privileges as $privilege) {
            if (!$source->isAllowed($privilege)) {
                return false;
            }
        }

        return true;
    }
}
