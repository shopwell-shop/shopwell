<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PaymentOrderRequest;
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
            'channel' => 'alipay_h5',
            'amount' => 2500,
            'currency' => 'cny',
            'subject' => '  T-Shirt  ',
            'body' => '',
            'attach' => 'user-1',
            'notify_url' => 'https://app.example.com/notify',
            'return_url' => 'https://app.example.com/return',
            'expire_minutes' => '15',
            'client_ip' => '10.0.0.1',
            'device' => 'wap',
            'idempotency_key' => 'key-1',
            'channel_params' => ['openid' => 'o-1'],
        ]);

        static::assertSame('T-1', $request->outTradeNo);
        static::assertSame('alipay_h5', $request->channel);
        static::assertSame(2500, $request->amount);
        static::assertSame('CNY', $request->currency);
        static::assertSame('T-Shirt', $request->subject);
        static::assertNull($request->body);
        static::assertSame('user-1', $request->attach);
        static::assertSame('https://app.example.com/notify', $request->notifyUrl);
        static::assertSame('https://app.example.com/return', $request->returnUrl);
        static::assertSame(15, $request->expireMinutes);
        static::assertSame('10.0.0.1', $request->clientIp);
        static::assertSame('wap', $request->device);
        static::assertSame('key-1', $request->idempotencyKey);
        static::assertSame(['openid' => 'o-1'], $request->channelParams);
    }

    public function testOnlyTheRequiredFieldsAreNeeded(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-2',
            'channel' => 'wechat_native',
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
        static::assertNull($request->device);
        static::assertNull($request->idempotencyKey);
        static::assertSame([], $request->channelParams);
    }

    public function testADigitStringAmountIsAccepted(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-3',
            'channel' => 'alipay_h5',
            'amount' => '500',
            'currency' => 'usd',
            'subject' => 'Order',
        ]);

        static::assertSame(500, $request->amount);
        static::assertSame('USD', $request->currency);
    }

    public function testRuntimeParamsAreFlattenedToScalarStrings(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-4',
            'channel' => 'wechat_jsapi',
            'amount' => 1,
            'currency' => 'CNY',
            'subject' => 'Order',
            'channel_params' => ['openid' => 'o-1', 'count' => 3, 'nested' => ['a' => 1], 'flag' => true],
        ]);

        static::assertSame(['openid' => 'o-1', 'count' => '3', 'flag' => '1'], $request->channelParams);
    }

    public function testNonScalarOptionalValuesBecomeNull(): void
    {
        $request = PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-5',
            'channel' => 'alipay_h5',
            'amount' => 1,
            'currency' => 'CNY',
            'subject' => 'Order',
            'body' => ['not' => 'a string'],
            'expire_minutes' => 'not-a-number',
            'channel_params' => 'not-an-array',
        ]);

        static::assertNull($request->body);
        static::assertNull($request->expireMinutes);
        static::assertSame([], $request->channelParams);
    }

    public function testAMissingOutTradeNoIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('out_trade_no'));

        PaymentOrderRequest::fromArray([
            'channel' => 'alipay_h5',
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
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => '   ',
        ]);
    }

    public function testAMissingChannelIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('channel'));

        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-7',
            'amount' => 100,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    public function testANonNumericAmountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::invalidAmount(0));

        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-8',
            'channel' => 'alipay_h5',
            'amount' => 'one hundred',
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    public function testANonPositiveAmountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::invalidAmount(0));

        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-9',
            'channel' => 'alipay_h5',
            'amount' => 0,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    public function testANegativeAmountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::invalidAmount(-10));

        PaymentOrderRequest::fromArray([
            'out_trade_no' => 'T-10',
            'channel' => 'alipay_h5',
            'amount' => -10,
            'currency' => 'CNY',
            'subject' => 'Order',
        ]);
    }

    public function testTheRequestCanBeConstructedDirectly(): void
    {
        $request = new PaymentOrderRequest('T-11', 'alipay_h5', 999, 'CNY', 'Order');

        static::assertSame('T-11', $request->outTradeNo);
        static::assertSame('alipay_h5', $request->channel);
        static::assertSame(999, $request->amount);
        static::assertSame('CNY', $request->currency);
        static::assertSame('Order', $request->subject);
        static::assertSame([], $request->channelParams);
    }
}
