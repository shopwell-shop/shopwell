<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Subscriber;

use Shopwell\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class AppScriptConditionConstraintsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'app_script_condition.loaded' => 'unserialize',
        ];
    }

    /**
     * @param EntityLoadedEvent<AppScriptConditionEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $entity) {
            $constraints = $entity->getConstraints();

            if (!\is_string($constraints)) {
                continue;
            }

            /** @phpstan-ignore shopwell.unserializeUsage */
            $entity->setConstraints(\unserialize($constraints));
        }
    }
}
