<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Cart\PaymentHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopwell\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.8.0 - will be removed without replacement
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DebitPayment::class)]
class DebitPaymentTest extends TestCase
{
    public function testPay(): void
    {
        $transactionId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $payment = new DebitPayment();
        $reponse = $payment->pay(
            new Request(),
            new PaymentTransactionStruct($transactionId),
            $context,
            null,
        );

        static::assertNull($reponse);
    }

    public function testSupports(): void
    {
        $payment = new DebitPayment();

        foreach (PaymentHandlerType::cases() as $case) {
            static::assertFalse($payment->supports(
                $case,
                Uuid::randomHex(),
                Context::createDefaultContext()
            ));
        }
    }
}
