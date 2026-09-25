<?php declare(strict_types=1);

namespace Shopwell\Core\System\UsageData\Subscriber;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Consent\ConsentStatus;
use Shopwell\Core\System\Consent\Definition\BackendData;
use Shopwell\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopwell\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\UsageData\Services\EntityDispatchService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentStateChangedSubscriber implements EventSubscriberInterface
{
    private const LEGACY_CONFIG_KEY = 'core.usageData.consentState';

    public function __construct(
        private readonly EntityDispatchService $entityDispatchService,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsentAcceptedEvent::class => 'handleConsentAcceptedEvent',
            ConsentRevokedEvent::class => 'handleConsentRevokedEvent',
        ];
    }

    public function handleConsentAcceptedEvent(ConsentAcceptedEvent $event): void
    {
        if ($event->consentName !== BackendData::NAME) {
            return;
        }

        $this->systemConfigService->set(self::LEGACY_CONFIG_KEY, ConsentStatus::ACCEPTED->value);

        $this->entityDispatchService->dispatchCollectEntityDataMessage();
    }

    public function handleConsentRevokedEvent(ConsentRevokedEvent $event): void
    {
        if ($event->consentName !== BackendData::NAME) {
            return;
        }

        $this->systemConfigService->set(self::LEGACY_CONFIG_KEY, ConsentStatus::REVOKED->value);
    }
}
