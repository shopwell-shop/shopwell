<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Cache\Script;

use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class ScriptCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ScriptExecutor $scriptExecutor)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'executeCacheInvalidationHook',
        ];
    }

    public function executeCacheInvalidationHook(EntityWrittenContainerEvent $event): void
    {
        $this->scriptExecutor->execute(
            new CacheInvalidationHook($event)
        );
    }
}
