<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppEvents;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Service\WebhookManager;
use Shopwell\Core\Framework\Webhook\WebhookCacheClearer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookCacheClearer::class)]
class WebhookCacheClearerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            AppEvents::APP_WRITTEN_EVENT => 'clearWebhookCache',
        ], WebhookCacheClearer::getSubscribedEvents());
    }

    public function testReset(): void
    {
        $manager = $this->createMock(WebhookManager::class);
        $manager->expects($this->once())
            ->method('clearInternalWebhookCache');

        $cacheClearer = new WebhookCacheClearer($manager);
        $cacheClearer->reset();
    }

    public function testClearWebhookCache(): void
    {
        $manager = $this->createMock(WebhookManager::class);
        $manager->expects($this->once())
            ->method('clearInternalWebhookCache');

        $cacheClearer = new WebhookCacheClearer($manager);
        $cacheClearer->clearWebhookCache();
    }
}
