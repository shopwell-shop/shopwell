<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphViolation;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\ViolationCategory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphJsonDecoder::class)]
class GraphJsonDecoderTest extends TestCase
{
    #[DataProvider('unsafeNumberProvider')]
    public function testRejectsNumbersThatWouldChangeValueWhenEncoded(string $token): void
    {
        $this->expectException(GraphJsonException::class);

        (new GraphJsonDecoder())->decode('{"config":{"amount":' . $token . '}}');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeNumberProvider(): iterable
    {
        yield 'integer outside safe range' => ['9007199254740992'];
        yield 'fraction rounds down onto safe boundary' => ['9007199254740991.1'];
        yield 'negative fraction rounds onto safe boundary' => ['-9007199254740991.1'];
        yield 'underflow would become zero' => ['1e-9999'];
        yield 'overflow would become infinity' => ['1e9999'];
        yield 'extra decimal precision would disappear' => ['0.10000000000000001'];
    }

    public function testEquivalentDecimalFormsAndNumericTextArePreserved(): void
    {
        $json = <<<'JSON'
{"number":1.25e2,"zero":-0,"text":"1e-9999 \" 9007199254740993","empty":{},"list":[],"none":null}
JSON;
        $decoded = (new GraphJsonDecoder())->decode($json);

        static::assertInstanceOf(\stdClass::class, $decoded);
        static::assertSame(125.0, $decoded->number);
        static::assertSame(0, $decoded->zero);
        static::assertSame('1e-9999 " 9007199254740993', $decoded->text);
        static::assertInstanceOf(\stdClass::class, $decoded->empty);
        static::assertSame([], $decoded->list);
        static::assertNull($decoded->none);
    }

    public function testDuplicateEscapedObjectKeysAreRejectedEvenWhenFirstValueIsNull(): void
    {
        $this->expectExceptionObject(new GraphJsonException(new GraphViolation(ViolationCategory::Structure, 'duplicate_json_key', '/nodes/0/config/key', 'Duplicate JSON object key.')));

        (new GraphJsonDecoder())->decode('{"nodes":[{"config":{"key":null,"\\u006bey":false}}]}');
    }

    public function testDuplicateKeysInDifferentObjectsDoNotConflict(): void
    {
        static::assertCount(2, (new GraphJsonDecoder())->decode('[{"id":1},{"id":2}]'));
    }

    public function testLargeJsonStringDoesNotExhaustTheTokenizerStack(): void
    {
        $text = str_repeat('approval text \\" ', 10000);

        static::assertSame($text, (new GraphJsonDecoder())->decode(json_encode($text, \JSON_THROW_ON_ERROR)));
    }

    public function testRejectsMalformedJsonAndRetainsTheParsingCause(): void
    {
        try {
            (new GraphJsonDecoder())->decode('{broken');
            static::fail('Malformed JSON must not decode.');
        } catch (GraphJsonException $exception) {
            static::assertInstanceOf(\JsonException::class, $exception->getPrevious());
            static::assertSame('invalid_json', $exception->violation->code);
        }
    }
}
