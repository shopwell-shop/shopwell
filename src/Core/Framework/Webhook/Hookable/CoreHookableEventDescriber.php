<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Hookable;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Hookable;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class CoreHookableEventDescriber implements HookableEventDescriber
{
    /**
     * @return list<HookableEventDescription>
     */
    public function describe(): array
    {
        return $this->getDescriptions();
    }

    public function describeForValidation(Manifest $manifest): array
    {
        return $this->getDescriptions();
    }

    /**
     * @return list<HookableEventDescription>
     */
    private function getDescriptions(): array
    {
        $events = [];

        foreach (Hookable::HOOKABLE_EVENTS as $eventClass => $eventName) {
            $events[] = new HookableEventDescription(
                $eventName,
                Hookable::HOOKABLE_EVENTS_DESCRIPTION[$eventClass],
                Hookable::HOOKABLE_EVENTS_PRIVILEGES[$eventClass]
            );
        }

        return $events;
    }
}
