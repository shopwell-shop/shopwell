<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler;
use Shopwell\Core\Framework\Webhook\Service\WebhookCleanup;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupWebhookEventLogTaskHandler::class)]
class CleanupWebhookEventLogTaskHandlerTest extends TestCase
{
    public function testHandler(): void
    {
        $cleaner = $this->createMock(WebhookCleanup::class);

        $cleaner->expects($this->once())->method('removeOldLogs');

        $handler = new CleanupWebhookEventLogTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $cleaner
        );

        $handler->run();
    }
}
