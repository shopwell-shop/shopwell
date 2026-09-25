<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextTokenResponse::class)]
class ContextTokenResponseTest extends TestCase
{
    public function testGetTokenFromResponseBody(): void
    {
        $token = 'sw-token-value';
        $response = new ContextTokenResponse($token);
        static::assertSame($token, $response->getToken());
    }

    public function testGetTokenFromHeader(): void
    {
        $token = 'sw-token-value';
        $response = new ContextTokenResponse($token);
        static::assertSame($token, $response->getToken());

        // It should be stored in a header instead
        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testGetRedirectUrlFromResponseBody(): void
    {
        $response = new ContextTokenResponse('sw-token-value', 'https://example.com/de');

        static::assertSame('https://example.com/de', $response->getRedirectUrl());
    }
}
