<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserOrganizationUnit;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Guarantees that a user has at most one primary organization unit.
 * When a mapping is written with is_primary = true, all other mappings
 * of the same user are reset to false (primary department semantics).
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class UserOrganizationUnitPrimarySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'user_organization_unit.written' => 'enforceSinglePrimary',
        ];
    }

    public function enforceSinglePrimary(EntityWrittenEvent $event): void
    {
        foreach ($event->getPayloads() as $payload) {
            if (($payload['isPrimary'] ?? false) !== true) {
                continue;
            }

            if (!isset($payload['userId'], $payload['organizationUnitId'])) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE `user_organization_unit`
                 SET `is_primary` = 0
                 WHERE `user_id` = :userId
                   AND `organization_unit_id` != :organizationUnitId
                   AND `is_primary` = 1',
                [
                    'userId' => Uuid::fromHexToBytes($payload['userId']),
                    'organizationUnitId' => Uuid::fromHexToBytes($payload['organizationUnitId']),
                ]
            );
        }
    }
}
