<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Notification;

use Shopwell\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Integration\IntegrationDefinition;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NotificationBulkEntityExtension extends BulkEntityExtension
{
    public function collect(): \Generator
    {
        yield IntegrationDefinition::ENTITY_NAME => [
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_integration_id', 'id'),
        ];

        yield UserDefinition::ENTITY_NAME => [
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_user_id', 'id'),
        ];
    }
}
