<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Payload;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Payload::class)]
class PayloadTest extends TestCase
{
    public function testStringReadsAScalarValue(): void
    {
        static::assertSame('trade-1', Payload::string(['out_trade_no' => 'trade-1'], 'out_trade_no'));
    }

    public function testStringCastsANonStringValue(): void
    {
        static::assertSame('42', Payload::string(['amount' => 42], 'amount'));
    }

    public function testStringFallsBackToTheDefaultForAMissingOrEmptyValue(): void
    {
        static::assertNull(Payload::string(['other' => 'x'], 'out_trade_no'));
        static::assertNull(Payload::string(['out_trade_no' => ''], 'out_trade_no'));
        static::assertNull(Payload::string(['out_trade_no' => null], 'out_trade_no'));
        static::assertNull(Payload::string(['out_trade_no' => ['nested']], 'out_trade_no'));

        static::assertSame('fallback', Payload::string(['out_trade_no' => ''], 'out_trade_no', 'fallback'));
    }

    public function testIntReadsAnIntAndANumericString(): void
    {
        static::assertSame(100, Payload::int(['amount' => 100], 'amount'));
        static::assertSame(100, Payload::int(['amount' => '100'], 'amount'));
        static::assertSame(-1, Payload::int(['amount' => '-1'], 'amount'));
    }

    public function testIntAnswersNullForAMissingOrNonNumericValue(): void
    {
        static::assertNull(Payload::int([], 'amount'));
        static::assertNull(Payload::int(['amount' => null], 'amount'));
        static::assertNull(Payload::int(['amount' => 'not-a-number'], 'amount'));
        static::assertNull(Payload::int(['amount' => ''], 'amount'));
        static::assertNull(Payload::int(['amount' => ['nested']], 'amount'));
    }

    public function testMapReadsANestedPayload(): void
    {
        static::assertSame(['currency' => 'CNY', 'value' => '10.00'], Payload::map(['amount' => ['currency' => 'CNY', 'value' => '10.00']], 'amount'));
    }

    public function testMapAnswersAnEmptyArrayForAnythingElse(): void
    {
        static::assertSame([], Payload::map([], 'amount'));
        static::assertSame([], Payload::map(['amount' => null], 'amount'));
        static::assertSame([], Payload::map(['amount' => '10.00'], 'amount'));
    }
}
