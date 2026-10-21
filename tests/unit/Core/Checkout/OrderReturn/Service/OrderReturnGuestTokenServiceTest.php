<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnGuestTokenService;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;

/**
 * A guest has no session, so the token is the only thing that keeps one visitor out of the return of
 * another. Everything that is not exactly a token this installation signed has to resolve to null.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnGuestTokenService::class)]
class OrderReturnGuestTokenServiceTest extends TestCase
{
    private const SECRET = 'a-secret-of-the-installation';

    public function testATokenResolvesToTheOrderItWasIssuedFor(): void
    {
        $service = new OrderReturnGuestTokenService(self::SECRET, new MockClock('2026-10-21 09:30:15'));

        $resolved = $service->resolve($service->issue('order-id', 'Customer@Example.com'));

        static::assertSame(['orderId' => 'order-id', 'email' => 'customer@example.com'], $resolved);
    }

    public function testAMissingTokenResolvesToNothing(): void
    {
        $service = new OrderReturnGuestTokenService(self::SECRET, new MockClock('2026-10-21 09:30:15'));

        static::assertNull($service->resolve(null));
        static::assertNull($service->resolve(''));
    }

    public function testSomethingThatIsNoTokenResolvesToNothing(): void
    {
        $service = new OrderReturnGuestTokenService(self::SECRET, new MockClock('2026-10-21 09:30:15'));

        static::assertNull($service->resolve('no-dot-in-here'));
        static::assertNull($service->resolve('too.many.parts'));
    }

    public function testATokenOfAnotherInstallationIsRejected(): void
    {
        $clock = new MockClock('2026-10-21 09:30:15');
        $foreign = new OrderReturnGuestTokenService('another-secret', $clock);
        $own = new OrderReturnGuestTokenService(self::SECRET, $clock);

        static::assertNull($own->resolve($foreign->issue('order-id', 'customer@example.com')));
    }

    public function testATamperedPayloadIsRejected(): void
    {
        $service = new OrderReturnGuestTokenService(self::SECRET, new MockClock('2026-10-21 09:30:15'));
        $token = $service->issue('order-id', 'customer@example.com');

        [$payload, $signature] = explode('.', $token);

        static::assertNull($service->resolve($payload . '.' . $signature . 'x'));
        static::assertNull($service->resolve('another-payload.' . $signature));
    }

    public function testATokenStopsWorkingAfterAWeek(): void
    {
        $clock = new MockClock('2026-10-21 09:30:15');
        $service = new OrderReturnGuestTokenService(self::SECRET, $clock);
        $token = $service->issue('order-id', 'customer@example.com');

        $clock->sleep(604800);

        static::assertNotNull($service->resolve($token), 'the token is still valid on the last second');

        $clock->sleep(1);

        static::assertNull($service->resolve($token));
    }

    public function testWhatIsStoredIsAHashOfTheToken(): void
    {
        $service = new OrderReturnGuestTokenService(self::SECRET, new MockClock('2026-10-21 09:30:15'));
        $token = $service->issue('order-id', 'customer@example.com');

        static::assertSame(hash('sha256', $token), $service->hash($token));
        static::assertNotSame($token, $service->hash($token));
    }
}
