<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Consent\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Consent\ConsentScope;
use Shopwell\Core\System\Consent\ConsentStatus;
use Shopwell\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopwell\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopwell\Core\System\Consent\Log\ConsentChangedSubscriber;
use Shopwell\Core\System\Consent\Log\ConsentLogInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentChangedSubscriber::class)]
class ConsentChangedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = ConsentChangedSubscriber::getSubscribedEvents();

        static::assertEquals([
            ConsentAcceptedEvent::class => 'onConsentAccepted',
            ConsentRevokedEvent::class => 'onConsentRevoked',
        ], $events);
    }

    public function testConsentAccepted(): void
    {
        $logger = static::createStub(ConsentLogInterface::class);
        $logger->method('log')->willReturnCallback(
            function (ConsentStatus $action, string $consentName, ?string $identifier, string $actor): void {
                static::assertSame(ConsentStatus::ACCEPTED, $action);
                static::assertSame('test-consent', $consentName);
                static::assertSame('identifier-123', $identifier);
                static::assertSame('actor-456', $actor);
            }
        );

        $event = new ConsentAcceptedEvent(
            'test-consent',
            ConsentScope\System::NAME,
            'identifier-123',
            'actor-456'
        );

        $subscriber = new ConsentChangedSubscriber($logger);
        $subscriber->onConsentAccepted($event);
    }

    public function testConsentRevoked(): void
    {
        $logger = static::createStub(ConsentLogInterface::class);
        $logger->method('log')->willReturnCallback(
            function (ConsentStatus $action, string $consentName, ?string $identifier, string $actor): void {
                static::assertSame(ConsentStatus::REVOKED, $action);
                static::assertSame('test-consent', $consentName);
                static::assertSame('identifier-123', $identifier);
                static::assertSame('actor-456', $actor);
            }
        );

        $event = new ConsentRevokedEvent(
            'test-consent',
            ConsentScope\System::NAME,
            'identifier-123',
            'actor-456'
        );

        $subscriber = new ConsentChangedSubscriber($logger);
        $subscriber->onConsentRevoked($event);
    }
}
