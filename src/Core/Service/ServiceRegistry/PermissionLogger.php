<?php declare(strict_types=1);

namespace Shopwell\Core\Service\ServiceRegistry;

use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Message\LogPermissionToRegistryMessage;
use Shopwell\Core\Service\Permission\ConsentState;
use Shopwell\Core\Service\Permission\PermissionsConsent;
use Shopwell\Core\Service\Permission\RemoteLogger;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
class PermissionLogger implements RemoteLogger
{
    public const CONFIG_STORE_LICENSE_HOST = 'core.store.licenseHost';

    public function __construct(
        private readonly Client $client,
        private readonly MessageBusInterface $messageBus,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function log(PermissionsConsent $consent, ConsentState $state): void
    {
        $this->messageBus->dispatch(new LogPermissionToRegistryMessage($consent, $state));
    }

    public function logSync(PermissionsConsent $consent, ConsentState $state): void
    {
        if ($state === ConsentState::GRANTED) {
            $this->client->saveConsent(
                new SaveConsentRequest(
                    identifier: $consent->identifier,
                    consentingUserId: $consent->consentingUserId,
                    shopIdentifier: $this->shopIdProvider->getShopId()->id,
                    consentDate: $consent->grantedAt->format(\DateTime::ATOM),
                    consentRevision: $consent->revision,
                    licenseHost: $this->systemConfigService->getString(self::CONFIG_STORE_LICENSE_HOST),
                )
            );
        }

        if ($state === ConsentState::REVOKED) {
            $this->client->revokeConsent($consent->identifier);
        }
    }
}
