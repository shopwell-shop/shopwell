<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Hookable;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;

/**
 * Describes the webhook events an area of the system offers to apps.
 *
 * A describer only says which events exist and what privileges they require. Who may
 * subscribe to or receive an event is decided by the policies covering it, see
 * Shopwell\Core\Framework\Webhook\Authorization\Policy\Policy.
 *
 * Implementations are discovered through the `shopwell.hookable_event.describer` tag.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface HookableEventDescriber
{
    /**
     * Every event this describer offers on the running system.
     *
     * @return list<HookableEventDescription>
     */
    public function describe(): array;

    /**
     * The events that exist once the given manifest is installed: everything describe()
     * returns, plus events the manifest itself brings into being. A manifest can only add
     * events here, never take them away.
     *
     * @return list<HookableEventDescription>
     */
    public function describeForValidation(Manifest $manifest): array;
}
