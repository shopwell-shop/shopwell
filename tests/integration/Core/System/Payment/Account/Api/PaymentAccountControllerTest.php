<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Account\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\PlatformRequest;

/**
 * @internal
 */
#[Package('framework')]
class PaymentAccountControllerTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('actions')]
    public function testEachAccountActionRequiresItsOwnPrivilege(string $action, string $privilege, string $method): void
    {
        $route = static::getContainer()->get('router')->getRouteCollection()->get($action);
        static::assertNotNull($route);
        static::assertSame([$privilege], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
        static::assertSame([$method], $route->getMethods());
        static::assertTrue($route->getDefault('auth_required'));
        static::assertSame(['administration'], $route->getDefault(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE));
    }

    public static function actions(): \Generator
    {
        yield 'list' => ['api.action.payment.accounts', 'payment_account:read', 'GET'];
        yield 'read' => ['api.action.payment.account.read', 'payment_account:read', 'GET'];
        yield 'create' => ['api.action.payment.account.create', 'payment_account:create', 'POST'];
        yield 'update' => ['api.action.payment.account.update', 'payment_account:update', 'PUT'];
        yield 'delete' => ['api.action.payment.account.delete', 'payment_account:delete', 'DELETE'];
    }
}
