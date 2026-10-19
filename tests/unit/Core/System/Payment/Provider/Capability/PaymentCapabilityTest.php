<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Capability;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentClose;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCapability::class)]
class PaymentCapabilityTest extends TestCase
{
    public function testNamesTheRequestOfEveryCapabilityThatHasOne(): void
    {
        static::assertSame(Capture::class, PaymentCapability::CAPTURE->requestClass());
        static::assertSame(PaymentQuery::class, PaymentCapability::QUERY->requestClass());
        static::assertSame(PaymentClose::class, PaymentCapability::CLOSE->requestClass());
        static::assertSame(Refund::class, PaymentCapability::REFUND->requestClass());
        static::assertSame(PaymentTransferRequest::class, PaymentCapability::TRANSFER->requestClass());
        static::assertSame(PaymentTransferQuery::class, PaymentCapability::TRANSFER_QUERY->requestClass());
        static::assertSame(Notify::class, PaymentCapability::NOTIFICATION->requestClass());
    }

    public function testThePolicyCapabilitiesHaveNoRequest(): void
    {
        static::assertNull(PaymentCapability::PARTIAL_REFUND->requestClass());
        static::assertNull(PaymentCapability::MANDATE->requestClass());
        static::assertNull(PaymentCapability::TRANSFER_NOTIFICATION->requestClass());
    }

    public function testTellsRequestCapabilitiesFromPolicyCapabilities(): void
    {
        static::assertTrue(PaymentCapability::CAPTURE->isRequestCapability());
        static::assertTrue(PaymentCapability::TRANSFER_QUERY->isRequestCapability());

        static::assertFalse(PaymentCapability::PARTIAL_REFUND->isRequestCapability());
        static::assertFalse(PaymentCapability::MANDATE->isRequestCapability());
        static::assertFalse(PaymentCapability::TRANSFER_NOTIFICATION->isRequestCapability());
    }

    /**
     * A capability that claims to be a request capability has to name a class that exists, otherwise
     * the declaration checker would accept an action nobody can dispatch.
     */
    public function testEveryNamedRequestClassExists(): void
    {
        foreach (PaymentCapability::cases() as $capability) {
            $requestClass = $capability->requestClass();

            if ($requestClass === null) {
                continue;
            }

            static::assertTrue(
                class_exists($requestClass),
                \sprintf('The capability "%s" names the request "%s", which does not exist.', $capability->value, $requestClass)
            );
        }
    }

    public function testHasExactlyTheTenCasesTheContractDocuments(): void
    {
        static::assertSame(
            [
                'capture',
                'query',
                'close',
                'refund',
                'transfer',
                'transfer_query',
                'notification',
                'partial_refund',
                'mandate',
                'transfer_notification',
            ],
            array_map(static fn (PaymentCapability $capability): string => $capability->value, PaymentCapability::cases())
        );
    }
}
