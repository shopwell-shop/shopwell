<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopwell\Core\Service\LifecycleManager;
use Shopwell\Core\Service\Subscriber\SystemUpdateSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemUpdateSubscriber::class)]
class SystemUpdateSubscriberTest extends TestCase
{
    public function testSyncDelegatesToLifecycleManager(): void
    {
        $context = new Context(new SystemSource());
        $lifecycleManager = $this->createMock(LifecycleManager::class);
        $lifecycleManager->expects($this->once())
            ->method('sync')
            ->with($context);

        $subscriber = new SystemUpdateSubscriber($lifecycleManager, static::createStub(LoggerInterface::class));
        $subscriber->sync(new UpdatePostFinishEvent($context, '6.7.0.0', '6.7.1.0'));
    }
}
