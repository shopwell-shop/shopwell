<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Rule\DataAbstractionLayer;

use Shopwell\Core\Content\Rule\RuleEntity;
use Shopwell\Core\Content\Rule\RuleEvents;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\Container;
use Shopwell\Core\Framework\Rule\Container\FilterRule;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\ScriptRule;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RulePayloadSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RulePayloadUpdater $updater,
        private readonly ContainerInterface $container,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RuleEvents::RULE_LOADED_EVENT => 'unserialize',
        ];
    }

    /**
     * @param EntityLoadedEvent<RuleEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        $this->indexIfNeeded($event);

        foreach ($event->getEntities() as $entity) {
            $payload = $entity->getPayload();
            if ($payload === null || !\is_string($payload)) {
                continue;
            }

            /** @phpstan-ignore shopwell.unserializeUsage */
            $payload = \unserialize($payload);

            $this->enrichConditions([$payload]);

            $entity->setPayload($payload);
        }
    }

    /**
     * @param EntityLoadedEvent<RuleEntity> $event
     */
    private function indexIfNeeded(EntityLoadedEvent $event): void
    {
        $rules = [];

        foreach ($event->getEntities() as $rule) {
            if ($rule->getPayload() === null && !$rule->isInvalid()) {
                $rules[$rule->getId()] = $rule;
            }
        }

        if ($rules === []) {
            return;
        }

        $updated = $this->updater->update(array_keys($rules));

        foreach ($updated as $id => $entity) {
            $rules[$id]->assign($entity);
        }
    }

    /**
     * @param list<Rule> $conditions
     */
    private function enrichConditions(array $conditions): void
    {
        foreach ($conditions as $condition) {
            if ($condition instanceof ScriptRule) {
                $condition->configureDependencies($this->container);

                continue;
            }

            if ($condition instanceof Container || $condition instanceof FilterRule) {
                $this->enrichConditions($condition->getRules());
            }
        }
    }
}
