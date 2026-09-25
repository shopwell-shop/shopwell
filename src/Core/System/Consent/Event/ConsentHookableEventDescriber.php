<?php declare(strict_types=1);

namespace Shopwell\Core\System\Consent\Event;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopwell\Core\System\Consent\ConsentDefinitionRegistry;

#[Package('data-services')]
class ConsentHookableEventDescriber implements HookableEventDescriber
{
    /**
     * @internal
     */
    public function __construct(private readonly ConsentDefinitionRegistry $consentDefinitionRegistry)
    {
    }

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

        foreach ($this->consentDefinitionRegistry->all() as $consentDefinition) {
            $consentName = $consentDefinition->getName();
            $privilege = \sprintf('consent:%s:%s', $consentName, AclRoleDefinition::PRIVILEGE_READ);

            $events[] = new HookableEventDescription(
                \sprintf('consent.%s.accepted', $consentName),
                \sprintf('Fires when the %s consent is accepted.', $consentName),
                [$privilege]
            );
            $events[] = new HookableEventDescription(
                \sprintf('consent.%s.revoked', $consentName),
                \sprintf('Fires when the %s consent is revoked.', $consentName),
                [$privilege]
            );
        }

        return $events;
    }
}
