<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentCheckoutBindingException;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCheckoutBindingException::class)]
class PaymentCheckoutBindingExceptionTest extends TestCase
{
    public function testPaymentMethodChannelTakenKeepsContractAndFacadeCompatibility(): void
    {
        $exception = PaymentCheckoutBindingException::paymentMethodChannelTaken('Payment App', 'alipay_page', 'method-id', 'Alipay');

        static::assertSame(PaymentCheckoutBindingException::class, $exception::class);
        static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertSame('PAYMENT__PAYMENT_METHOD_CHANNEL_TAKEN', $exception->getErrorCode());
        static::assertSame('The payment app "Payment App" already collects the channel "alipay_page" for the payment method "Alipay" (method-id).', $exception->getMessage());
        static::assertSame([
            'paymentAppName' => 'Payment App',
            'channelTechnicalName' => 'alipay_page',
            'paymentMethodId' => 'method-id',
            'paymentMethodName' => 'Alipay',
        ], $exception->getParameters());

        $facade = PaymentException::paymentMethodChannelTaken('Payment App', 'alipay_page', 'method-id', 'Alipay');

        static::assertSame(PaymentCheckoutBindingException::class, $facade::class);
        static::assertEquals($exception, $facade);
    }
}
