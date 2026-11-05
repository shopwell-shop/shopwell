<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderRequest::class)]
class PaymentOrderRequestTest extends TestCase
{
    public function testEveryFieldIsReadFromThePayload(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-1',
            'amount' => 2500,
            'currency' => 'cny',
            'subject' => '  T-Shirt  ',
            'body' => '',
            'attach' => 'user-1',
            'notify_url' => 'https://app.example.com/notify',
            'return_url' => 'https://app.example.com/return',
            'expire_minutes' => '15',
            'client_ip' => '10.0.0.1',
            'idempotency_key' => 'key-1',
        ]);

        static::assertSame('T-1', $request->outTradeNo);
        static::assertSame(2500, $request->amount);
        static::assertSame('CNY', $request->currency);
        static::assertSame('T-Shirt', $request->subject);
        static::assertNull($request->body);
        static::assertSame('user-1', $request->attach);
        static::assertSame('https://app.example.com/notify', $request->notifyUrl);
        static::assertSame('https://app.example.com/return', $request->returnUrl);
        static::assertSame(15, $request->expireMinutes);
        static::assertSame('10.0.0.1', $request->clientIp);
        static::assertSame('key-1', $request->idempotencyKey);
    }

    public function testOnlyTheRequiredFieldsAreNeeded(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-2',
            'amount' => 100,
            'currency' => 'EUR',
            'subject' => 'Order',
        ]);

        static::assertSame('T-2', $request->outTradeNo);
        static::assertSame(100, $request->amount);
        static::assertSame('EUR', $request->currency);
        static::assertNull($request->body);
        static::assertNull($request->attach);
        static::assertNull($request->notifyUrl);
        static::assertNull($request->returnUrl);
        static::assertNull($request->expireMinutes);
        static::assertNull($request->clientIp);
        static::assertNull($request->idempotencyKey);
    }

    public function testADigitStringAmountIsAccepted(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-3',
            'amount' => '500',
            'currency' => 'usd',
            'subject' => 'Order',
        ]);

        static::assertSame(500, $request->amount);
        static::assertSame('USD', $request->currency);
    }

    public function testNonScalarOptionalValuesBecomeNull(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-5',
            'amount' => 1,
            'currency' => 'CNY',
            'subject' => 'Order',
            'body' => ['not' => 'a string'],
            'expire_minutes' => 'not-a-number',
        ]);

        static::assertNull($request->body);
        static::assertNull($request->expireMinutes);
    }

    public function testAMissingOutTradeNoIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('out_trade_no'));

        PaymentOrderRequest::fromArray([
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    public function testABlankSubjectIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('subject'));

        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-6',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '   ',
        ]);
    }

    public function testAnObligationCanBeCreatedBeforeSelectingAProduct(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-7',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
        static::assertSame('T-7', $request->outTradeNo);
        static::assertSame(100, $request->amount);
    }

    public function testANonNumericAmountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentOrderException::invalidAmount(0));

        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-8',
            'amount' => 'one hundred',
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    public function testANonPositiveAmountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentOrderException::invalidAmount(0));

        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-9',
            'amount' => 0,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    public function testANegativeAmountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentOrderException::invalidAmount(-10));

        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-10',
            'amount' => -10,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidIntegerAmounts(): iterable
    {
        yield 'fractional string' => ['12.50'];
        yield 'fractional number' => [12.5];
        yield 'overflow' => [(string) \PHP_INT_MAX . '0'];
        yield 'boolean' => [true];
        yield 'scientific notation' => ['1e3'];
    }

    #[DataProvider('invalidIntegerAmounts')]
    public function testInvalidIntegerAmountsAreRejected(mixed $amount): void
    {
        $this->expectException(PaymentOrderException::class);
        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-invalid',
            'amount' => $amount,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    public function testTheRequestCanBeConstructedDirectly(): void
    {
        $request = new PaymentOrderRequest('T-11', 999, 'CNY', 'Order');

        static::assertSame('T-11', $request->outTradeNo);
        static::assertSame(999, $request->amount);
        static::assertSame('CNY', $request->currency);
        static::assertSame('Order', $request->subject);
    }
}
