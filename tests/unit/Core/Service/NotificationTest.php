<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Notification\NotificationDefinition;
use Shopwell\Core\Framework\Notification\NotificationService;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Service\Notification;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Notification::class)]
class NotificationTest extends TestCase
{
    public function testDelegatesNewServicesInstalledToNotificationService(): void
    {
        $repo = new StaticEntityRepository([], new NotificationDefinition());

        $notification = new Notification(new NotificationService($repo));

        $notification->newServicesInstalled();

        static::assertNotEmpty($repo->creates);
        static::assertCount(1, $repo->creates);

        $createdNotification = $repo->creates[0][0];

        static::assertTrue(Uuid::isValid($createdNotification['id']));
        static::assertEquals('New services have been installed. Reload your administration to see what\'s new.', $createdNotification['message']);
        static::assertEquals('positive', $createdNotification['status']);
        static::assertTrue($createdNotification['adminOnly']);
        static::assertEquals(['system.plugin_maintain'], $createdNotification['requiredPrivileges']);
    }
}
