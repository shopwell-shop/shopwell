<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierRegistry;

/**
 * The registry is keyed by the interface that signed the notification, because the url names that
 * interface and not the channel an order was paid with.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferNotificationVerifierRegistry::class)]
class PaymentTransferNotificationVerifierRegistryTest extends TestCase
{
    public function testItKnowsEveryVerifierItWasGiven(): void
    {
        $registry = new PaymentTransferNotificationVerifierRegistry([
            $this->verifier('alipay'),
            $this->verifier('stripe'),
        ]);

        static::assertTrue($registry->has('alipay'));
        static::assertTrue($registry->has('stripe'));
    }

    public function testAnInterfaceWithoutAVerifierIsNotKnown(): void
    {
        $registry = new PaymentTransferNotificationVerifierRegistry([$this->verifier('alipay')]);

        static::assertFalse($registry->has('paypal'));
    }

    public function testGetReturnsTheVerifierOfThatProvider(): void
    {
        $alipay = $this->verifier('alipay');
        $registry = new PaymentTransferNotificationVerifierRegistry([$alipay, $this->verifier('stripe')]);

        static::assertSame($alipay, $registry->get('alipay'));
    }

    public function testAnUnknownProviderCannotBeVerified(): void
    {
        $registry = new PaymentTransferNotificationVerifierRegistry([$this->verifier('alipay')]);

        $this->expectExceptionObject(PaymentException::transferNotificationProviderUnknown('paypal'));

        $registry->get('paypal');
    }

    public function testAnEmptyRegistryKnowsNothing(): void
    {
        $registry = new PaymentTransferNotificationVerifierRegistry([]);

        static::assertFalse($registry->has('alipay'));
    }

    private function verifier(string $provider): PaymentTransferNotificationVerifierInterface
    {
        $verifier = static::createStub(PaymentTransferNotificationVerifierInterface::class);
        $verifier->method('provider')->willReturn($provider);

        return $verifier;
    }
}
