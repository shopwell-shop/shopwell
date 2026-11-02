<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Subscriber;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopwell\Core\System\AdminMenu\Service\PluginMenuSynchronizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hooks the navigation of a plugin into its lifecycle, so a plugin declares its entries
 * once and never writes them itself.
 *
 * Uninstalling is handled on the Pre event, like the theme subscriber does: the decision
 * to keep or drop the rows is taken before any resource of the plugin is removed.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class PluginMenuLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly PluginMenuSynchronizer $synchronizer)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'onInstall',
            PluginPostActivateEvent::class => 'onActivate',
            PluginPostDeactivateEvent::class => 'onDeactivate',
            PluginPostUpdateEvent::class => 'onUpdate',
            PluginPreUninstallEvent::class => 'onUninstall',
        ];
    }

    public function onInstall(PluginPostInstallEvent $event): void
    {
        $this->synchronizer->syncInstall($event->getPlugin());
    }

    public function onActivate(PluginPostActivateEvent $event): void
    {
        $this->synchronizer->syncActivation($event->getPlugin());
    }

    public function onDeactivate(PluginPostDeactivateEvent $event): void
    {
        $this->synchronizer->syncDeactivation($event->getPlugin());
    }

    public function onUpdate(PluginPostUpdateEvent $event): void
    {
        $this->synchronizer->syncUpdate($event->getPlugin());
    }

    public function onUninstall(PluginPreUninstallEvent $event): void
    {
        $this->synchronizer->syncUninstall($event->getPlugin(), $event->getContext()->keepUserData());
    }
}
