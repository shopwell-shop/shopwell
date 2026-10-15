<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\PaymentApiRouteScope;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentApiRouteScope::class)]
class PaymentApiRouteScopeTest extends TestCase
{
    public function testTheScopeIdentifiersAreStable(): void
    {
        static::assertSame('payment-api', (new PaymentApiRouteScope())->getId());
        static::assertSame(['payment-api'], (new PaymentApiRouteScope())->getRoutePrefixes());
    }

    public function testIdReturnsTheScopeId(): void
    {
        static::assertSame(PaymentApiRouteScope::ID, (new PaymentApiRouteScope())->getId());
    }

    public function testItAllowsEveryRequest(): void
    {
        $scope = new PaymentApiRouteScope();

        static::assertTrue($scope->isAllowed(new Request()));
        static::assertTrue($scope->isAllowed(Request::create('/payment-api/create')));
    }

    public function testItOnlyAllowsPathsBelowItsPrefix(): void
    {
        $scope = new PaymentApiRouteScope();

        static::assertTrue($scope->isAllowedPath('/payment-api'));
        static::assertTrue($scope->isAllowedPath('/payment-api/create'));
        static::assertFalse($scope->isAllowedPath('/store-api/payment'));
        static::assertFalse($scope->isAllowedPath('/'));
    }

    public function testTheRoutePrefixesAreTheAllowedPaths(): void
    {
        static::assertSame([PaymentApiRouteScope::ALLOWED_PATH], (new PaymentApiRouteScope())->getRoutePrefixes());
    }
}
