<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferException::class)]
class PaymentTransferExceptionTest extends TestCase
{
    public function testTransferOutNoTakenKeepsContract(): void
    {
        $exception = PaymentTransferException::transferOutNoTaken('payout-1');

        static::assertSame(PaymentTransferException::class, $exception::class);
        static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertSame('PAYMENT__TRANSFER_OUT_NO_TAKEN', $exception->getErrorCode());
        static::assertSame('The out number "payout-1" is already used by another payout of this app.', $exception->getMessage());
        static::assertSame(['outNo' => 'payout-1'], $exception->getParameters());
    }
}
