<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Subscriber;

use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemRole\AdminMenuItemRoleDefinition;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation\AdminMenuItemTranslationDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates the cached visible navigation whenever the data behind it changes: the
 * items themselves, their translations and role bindings, and the ACL roles a binding
 * points at. The plugin lifecycle invalidates the same tag through the synchronizer.
 *
 * The tag is also the placeholder the visible tree caching builds on; the read side can
 * key its entries by whatever it likes, as long as it tags them with {@link CACHE_TAG}.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class AdminMenuCacheSubscriber implements EventSubscriberInterface
{
    final public const CACHE_TAG = 'admin-menu';

    /**
     * @internal
     */
    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'invalidateOnWrite',
        ];
    }

    public function invalidateOnWrite(EntityWrittenContainerEvent $event): void
    {
        $relevant = $event->getEventByEntityName(AdminMenuItemDefinition::ENTITY_NAME)
            || $event->getEventByEntityName(AdminMenuItemTranslationDefinition::ENTITY_NAME)
            || $event->getEventByEntityName(AdminMenuItemRoleDefinition::ENTITY_NAME)
            || $event->getEventByEntityName(AclRoleDefinition::ENTITY_NAME);

        if (!$relevant) {
            return;
        }

        $this->invalidate();
    }

    public function invalidate(): void
    {
        $this->cacheInvalidator->invalidate([self::CACHE_TAG], true);
    }
}
