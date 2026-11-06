<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Context\ActivateContext;
use Shopwell\Core\Framework\Plugin\Context\DeactivateContext;
use Shopwell\Core\Framework\Plugin\Context\InstallContext;
use Shopwell\Core\Framework\Plugin\Context\UninstallContext;
use Shopwell\Core\Framework\Plugin\Context\UpdateContext;
use Shopwell\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopwell\Core\Framework\Plugin\PluginEntity;
use Shopwell\Core\System\AdminMenu\Service\PluginMenuSynchronizer;
use Shopwell\Core\System\AdminMenu\Subscriber\PluginMenuLifecycleSubscriber;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(PluginMenuLifecycleSubscriber::class)]
class PluginMenuLifecycleSubscriberTest extends TestCase
{
    public function testRoutesLifecycleEventsToTheirSynchronizationActions(): void
    {
        $plugin = new PluginEntity();
        $sync = $this->createMock(PluginMenuSynchronizer::class);
        foreach (['syncInstall', 'syncActivation', 'syncDeactivation', 'syncUpdate'] as $action) {
            $sync->expects($this->once())->method($action)->with($plugin);
        }
        $subscriber = new PluginMenuLifecycleSubscriber($sync);
        $subscriber->onInstall(new PluginPostInstallEvent($plugin, static::createStub(InstallContext::class)));
        $subscriber->onActivate(new PluginPostActivateEvent($plugin, static::createStub(ActivateContext::class)));
        $subscriber->onDeactivate(new PluginPostDeactivateEvent($plugin, static::createStub(DeactivateContext::class)));
        $subscriber->onUpdate(new PluginPostUpdateEvent($plugin, static::createStub(UpdateContext::class)));
        static::assertSame([
            PluginPostInstallEvent::class => 'onInstall',
            PluginPostActivateEvent::class => 'onActivate',
            PluginPostDeactivateEvent::class => 'onDeactivate',
            PluginPostUpdateEvent::class => 'onUpdate',
            PluginPreUninstallEvent::class => 'onUninstall',
        ], PluginMenuLifecycleSubscriber::getSubscribedEvents());
    }

    #[DataProvider('keepDataCases')]
    public function testUninstallHonorsTheKeepUserDataDecision(bool $keepData): void
    {
        $plugin = new PluginEntity();
        $context = static::createStub(UninstallContext::class);
        $context->method('keepUserData')->willReturn($keepData);
        $sync = $this->createMock(PluginMenuSynchronizer::class);
        $sync->expects($this->once())->method('syncUninstall')->with($plugin, $keepData);
        static::assertSame('onUninstall', PluginMenuLifecycleSubscriber::getSubscribedEvents()[PluginPreUninstallEvent::class]);
        (new PluginMenuLifecycleSubscriber($sync))->onUninstall(new PluginPreUninstallEvent($plugin, $context));
    }

    /**
     * @return iterable<array{bool}>
     */
    public static function keepDataCases(): iterable
    {
        yield [false];
        yield [true];
    }
}
