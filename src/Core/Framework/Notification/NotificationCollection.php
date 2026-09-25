<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Notification;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NotificationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[ClassMoved(version: 'v6.8.0', previousClassName: 'Shopwell\Administration\Notification\NotificationCollection')]
class NotificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationEntity::class;
    }
}
