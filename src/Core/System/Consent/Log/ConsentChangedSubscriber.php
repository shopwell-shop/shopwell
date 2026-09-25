<?php declare(strict_types=1);

namespace Shopwell\Core\System\Consent\Log;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Consent\ConsentStatus;
use Shopwell\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopwell\Core\System\Consent\Event\ConsentRevokedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?ConsentLogInterface $consentChangeLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsentAcceptedEvent::class => 'onConsentAccepted',
            ConsentRevokedEvent::class => 'onConsentRevoked',
        ];
    }

    public function onConsentAccepted(ConsentAcceptedEvent $event): void
    {
        $this->logConsentChange(ConsentStatus::ACCEPTED, $event->consentName, $event->identifier, $event->actor);
    }

    public function onConsentRevoked(ConsentRevokedEvent $event): void
    {
        $this->logConsentChange(ConsentStatus::REVOKED, $event->consentName, $event->identifier, $event->actor);
    }

    private function logConsentChange(ConsentStatus $consentStatus, string $consentName, string $identifier, string $actor): void
    {
        if ($this->consentChangeLogger === null) {
            return;
        }

        $this->consentChangeLogger->log(
            $consentStatus,
            $consentName,
            $identifier,
            $actor,
        );
    }
}
