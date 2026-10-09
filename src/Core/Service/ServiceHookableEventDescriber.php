<?php declare(strict_types=1);

namespace Shopwell\Core\Service;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopwell\Core\Service\Event\CommercialLicenseProvidedEvent;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ServiceHookableEventDescriber implements HookableEventDescriber
{
    public function describe(): array
    {
        return $this->getServiceEventDescriptions();
    }

    public function describeForValidation(Manifest $manifest): array
    {
        return $this->getServiceEventDescriptions();
    }

    /**
     * @return list<HookableEventDescription>
     */
    private function getServiceEventDescriptions(): array
    {
        return [
            new HookableEventDescription(
                CommercialLicenseProvidedEvent::NAME,
                'Fires when the current commercial license data is provided to services.',
                []
            ),
        ];
    }
}
