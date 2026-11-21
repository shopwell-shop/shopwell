<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\OAuth\ShopwellAuthorizationRequest;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwellAuthorizationRequest::class)]
class ShopwellAuthorizationRequestTest extends TestCase
{
    public function testResourceIsEmptyUntilSet(): void
    {
        $request = new ShopwellAuthorizationRequest();
        static::assertNull($request->getResource());

        $request->setResource('https://shop.example/api/_mcp');
        static::assertSame('https://shop.example/api/_mcp', $request->getResource());

        $request->setResource(null);
        static::assertNull($request->getResource());
    }
}
