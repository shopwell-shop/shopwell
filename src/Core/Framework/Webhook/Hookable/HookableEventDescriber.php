<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Hookable;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;

/**
 * This interface describes how additional webhook event names and their
 * required privileges are provided to the app-system.
 *
 * Implementations are discovered through the `shopwell.hookable_event.describer` tag.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface HookableEventDescriber
{
    /**
     *  Use describe() to list the events based on the current running system configuration.
     *
     * @return list<HookableEventDescription>
     */
    public function describe(): array;

    /**
     * Use describeForValidation() to provide the full list of events for manifest validation. If events are generated based off other configurations, then this method can use the manifest to list those dynamic events, which are not part of the running system yet.
     *
     * @return list<HookableEventDescription>
     */
    public function describeForValidation(Manifest $manifest): array;
}
