<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Struct\PaymentRefundRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRefundRequest::class)]
class PaymentRefundRequestTest extends TestCase
{
    public function testEveryFieldIsReadFromThePayload(): void
    {
        $request = PaymentRefundRequest::fromArray([
            'out_refund_no' => '  R-1  ',
            'amount' => '250',
            'reason' => '  customer request  ',
        ]);

        static::assertSame('R-1', $request->outRefundNo);
        static::assertSame(250, $request->amount);
        static::assertSame('customer request', $request->reason);
    }

    public function testTheReasonIsOptional(): void
    {
        $request = PaymentRefundRequest::fromArray([
            'out_refund_no' => 'R-2',
            'amount' => 100,
        ]);

        static::assertSame('R-2', $request->outRefundNo);
        static::assertSame(100, $request->amount);
        static::assertNull($request->reason);
    }

    public function testABlankReasonBecomesNull(): void
    {
        $request = PaymentRefundRequest::fromArray([
            'out_refund_no' => 'R-3',
            'amount' => 100,
            'reason' => '   ',
        ]);

        static::assertNull($request->reason);
    }

    public function testANonScalarReasonBecomesNull(): void
    {
        $request = PaymentRefundRequest::fromArray([
            'out_refund_no' => 'R-4',
            'amount' => 100,
            'reason' => ['not', 'scalar'],
        ]);

        static::assertNull($request->reason);
    }

    public function testAnIntegerAmountKeepsItsValue(): void
    {
        $request = PaymentRefundRequest::fromArray([
            'out_refund_no' => 'R-5',
            'amount' => 500,
        ]);

        static::assertSame(500, $request->amount);
    }

    public function testAMissingOutRefundNoIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('out_refund_no'));

        PaymentRefundRequest::fromArray(['amount' => 100]);
    }

    public function testABlankOutRefundNoIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('out_refund_no'));

        PaymentRefundRequest::fromArray(['out_refund_no' => '   ', 'amount' => 100]);
    }

    public function testAMissingAmountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('amount'));

        PaymentRefundRequest::fromArray(['out_refund_no' => 'R-6']);
    }

    public function testANonNumericAmountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('amount'));

        PaymentRefundRequest::fromArray(['out_refund_no' => 'R-7', 'amount' => 'ten']);
    }

    public function testTheRequestCanBeConstructedDirectly(): void
    {
        $request = new PaymentRefundRequest('R-8', 300);

        static::assertSame('R-8', $request->outRefundNo);
        static::assertSame(300, $request->amount);
        static::assertNull($request->reason);
    }
}
