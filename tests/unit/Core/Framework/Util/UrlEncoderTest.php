<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\UrlEncoder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UrlEncoder::class)]
class UrlEncoderTest extends TestCase
{
    public function testHappyPath(): void
    {
        $url = 'https://shopwell.cn:80/some/thing';
        static::assertSame($url, UrlEncoder::encodeUrl($url));
    }

    public function testReturnsNullIfNoUrlIsGiven(): void
    {
        static::assertNull(UrlEncoder::encodeUrl(null));
    }

    public function testItEncodesWithoutPort(): void
    {
        $url = 'https://shopwell.cn/some/thing';
        static::assertSame($url, UrlEncoder::encodeUrl($url));
    }

    public function testRespectsQueryParameter(): void
    {
        $url = 'https://shopwell.cn/some/thing?a=3&b=25';
        static::assertSame($url, UrlEncoder::encodeUrl($url));
    }

    public function testReturnsEncodedPathsWithoutHostAndScheme(): void
    {
        static::assertSame(
            'shopwell.cn/some/thing',
            UrlEncoder::encodeUrl('shopwell.cn/some/thing')
        );
    }

    public function testItEncodesSpaces(): void
    {
        static::assertSame(
            'https://shopwell.cn:80/so%20me/thing%20new.jpg',
            UrlEncoder::encodeUrl('https://shopwell.cn:80/so me/thing new.jpg')
        );
    }

    public function testItEncodesSpecialCharacters(): void
    {
        static::assertSame(
            'https://shopwell.cn:80/so%20me/thing%20new.jpg',
            UrlEncoder::encodeUrl('https://shopwell.cn:80/so me/thing new.jpg')
        );
    }

    public function testItEncodesUmlautsAndSpecialCharacters(): void
    {
        static::assertSame(
            'https://shopwell.cn/path/%C3%A4%C3%B6%C3%BC%20test.jpg',
            UrlEncoder::encodeUrl('https://shopwell.cn/path/äöü test.jpg')
        );
    }

    public function testItHandlesComplexUrls(): void
    {
        static::assertSame(
            'https://example.com:8080/path/with%20spaces/and%20%28brackets%29/file%20name.jpg?param=value&other=test',
            UrlEncoder::encodeUrl('https://example.com:8080/path/with spaces/and (brackets)/file name.jpg?param=value&other=test')
        );
    }

    public function testItHandlesUrlsWithOnlyPath(): void
    {
        static::assertSame(
            '/media/folder/file%20with%20spaces.jpg',
            UrlEncoder::encodeUrl('/media/folder/file with spaces.jpg')
        );
    }

    public function testItReturnsEmptyStringForEmptyInput(): void
    {
        static::assertSame('', UrlEncoder::encodeUrl(''));
    }

    public function testItHandlesUrlsWithoutFragment(): void
    {
        static::assertSame(
            'https://shopwell.cn/path/file%20name.jpg',
            UrlEncoder::encodeUrl('https://shopwell.cn/path/file name.jpg#section')
        );
    }

    public function testItReturnsNullForMalformedUrls(): void
    {
        static::assertNull(UrlEncoder::encodeUrl('http://shopwell.cn:notaport/media/file.jpg'));
    }

    public function testItHandlesRelativePaths(): void
    {
        static::assertSame(
            '../media/file%20name.jpg',
            UrlEncoder::encodeUrl('../media/file name.jpg')
        );
    }

    /**
     * `parse_url()` replaces every byte the current libc reports as a control character with `_`.
     * On platforms where that covers 0x7F-0x9F it destroys the continuation bytes of these
     * characters, so the encoder must not rebuild the URL from `parse_url()` components.
     */
    #[DataProvider('nonAsciiFileNameProvider')]
    public function testItIdempotentEncodesNonAsciiFileNamesWithoutCorruption(string $url, string $expected): void
    {
        static::assertSame($expected, UrlEncoder::encodeUrl($expected));
        static::assertSame($expected, UrlEncoder::encodeUrl($url));
    }

    public static function nonAsciiFileNameProvider(): \Generator
    {
        yield 'uppercase umlauts and sharp s survive encoding' => [
            'https://shopwell.cn/media/Ärmel Öl Übung ß.jpg',
            'https://shopwell.cn/media/%C3%84rmel%20%C3%96l%20%C3%9Cbung%20%C3%9F.jpg',
        ];

        yield 'typographic punctuation and currency signs survive encoding' => [
            'https://shopwell.cn/media/Größe – „Zitat“ €.jpg',
            'https://shopwell.cn/media/Gr%C3%B6%C3%9Fe%20%E2%80%93%20%E2%80%9EZitat%E2%80%9C%20%E2%82%AC.jpg',
        ];

        yield 'uppercase accented latin characters survive encoding' => [
            'https://shopwell.cn/media/ÀÉÎÕÇ.jpg',
            'https://shopwell.cn/media/%C3%80%C3%89%C3%8E%C3%95%C3%87.jpg',
        ];

        yield 'cyrillic characters survive encoding' => [
            'https://shopwell.cn/media/Тест.jpg',
            'https://shopwell.cn/media/%D0%A2%D0%B5%D1%81%D1%82.jpg',
        ];

        yield 'multi byte characters survive encoding' => [
            'https://shopwell.cn/media/テスト.jpg',
            'https://shopwell.cn/media/%E3%83%86%E3%82%B9%E3%83%88.jpg',
        ];

        yield 'cache busting query is kept next to an encoded file name' => [
            'https://shopwell.cn/media/ab/cd/ef/Ärmel.jpg?ts=1755000000',
            'https://shopwell.cn/media/ab/cd/ef/%C3%84rmel.jpg?ts=1755000000',
        ];
    }

    #[DataProvider('nonAsciiPathProvider')]
    public function testEncodePathSegmentsEncodesRawPaths(string $path, string $expected): void
    {
        static::assertSame($expected, UrlEncoder::encodePathSegments($path));
    }

    public static function nonAsciiPathProvider(): \Generator
    {
        yield 'uppercase umlauts and sharp s' => [
            'media/ab/cd/Ärmel Öl ß.jpg',
            'media/ab/cd/%C3%84rmel%20%C3%96l%20%C3%9F.jpg',
        ];

        yield 'typographic punctuation and currency signs' => [
            'media/ab/cd/Größe – €.jpg',
            'media/ab/cd/Gr%C3%B6%C3%9Fe%20%E2%80%93%20%E2%82%AC.jpg',
        ];

        yield 'multi byte characters' => [
            'media/ab/cd/テスト.jpg',
            'media/ab/cd/%E3%83%86%E3%82%B9%E3%83%88.jpg',
        ];

        yield 'reserved characters are encoded' => [
            'media/ab/cd/a+b,c;d=e(f).jpg',
            'media/ab/cd/a%2Bb%2Cc%3Bd%3De%28f%29.jpg',
        ];
    }

    public function testItEncodesPercentSignsThatAreNotAnEscapeSequence(): void
    {
        static::assertSame(
            'https://shopwell.cn/media/50%25.jpg',
            UrlEncoder::encodeUrl('https://shopwell.cn/media/50%.jpg')
        );

        static::assertSame(
            'https://shopwell.cn/media/a%252Gb.jpg',
            UrlEncoder::encodeUrl('https://shopwell.cn/media/a%2Gb.jpg')
        );
    }

    public function testItKeepsEncodedPathSeparatorsInsideASegment(): void
    {
        static::assertSame(
            'https://cdn.example.com/bucket/object%2Fid%20%C3%84.jpg',
            UrlEncoder::encodeUrl('https://cdn.example.com/bucket/object%2Fid Ä.jpg')
        );
    }

    public function testItKeepsTheAuthorityUntouched(): void
    {
        static::assertSame(
            'https://user:pass@shopwell.cn:8080/media/%C3%84.jpg',
            UrlEncoder::encodeUrl('https://user:pass@shopwell.cn:8080/media/Ä.jpg')
        );
    }

    public function testItKeepsProtocolRelativeUrls(): void
    {
        static::assertSame(
            '//shopwell.cn/media/file%20name.jpg',
            UrlEncoder::encodeUrl('//shopwell.cn/media/file name.jpg')
        );
    }

    public function testEncodePathSegmentsKeepsNonAsciiCharacters(): void
    {
        static::assertSame(
            'media/foo/%C3%84rmel%20bild.jpg',
            UrlEncoder::encodePathSegments('media/foo/Ärmel bild.jpg')
        );
    }

    public function testEncodePathSegmentsEncodesSpecialCharacters(): void
    {
        static::assertSame(
            'media/foo/my%20file.jpg',
            UrlEncoder::encodePathSegments('media/foo/my file.jpg')
        );
    }

    public function testEncodePathSegmentsTreatsPercentSignsAsLiteralStorageCharacters(): void
    {
        static::assertSame(
            'media/ab/cd/50%2520off.jpg',
            UrlEncoder::encodePathSegments('media/ab/cd/50%20off.jpg')
        );

        static::assertSame(
            'media/ab/cd/50%252Foff.jpg',
            UrlEncoder::encodePathSegments('media/ab/cd/50%2Foff.jpg')
        );
    }
}
