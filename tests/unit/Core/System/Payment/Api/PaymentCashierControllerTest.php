<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\PaymentCashierController;
use Shopwell\Core\System\Payment\Api\PaymentContextParser;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierService;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierSessionEntity;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCashierController::class)]
class PaymentCashierControllerTest extends TestCase
{
    public function testRejectsUnauthenticatedCreatesBeforeTheCashierIsCalled(): void
    {
        $cashier = $this->createMock(PaymentCashierService::class);
        $cashier->expects($this->never())->method('create');
        $controller = new PaymentCashierController(PaymentControllerFixture::authenticator([]), $cashier, new PaymentContextParser());
        $this->expectExceptionObject(PaymentAppException::missingAuthParameters(['app_id']));
        $controller->create(new Request());
    }

    public function testCreatesASessionUsingTheAuthenticatedAppAndParsedContext(): void
    {
        $app = PaymentControllerFixture::app('client', 'secret');
        $payload = ['profile' => ' checkout ', 'currency' => 'CNY', 'context' => ['scene' => ['name' => 'browser', 'device' => 'web', 'environment' => 'browser']]];
        $request = PaymentControllerFixture::signedRequest('client', 'secret', 'POST', '/payment-api/v1/cashier-sessions', $payload);
        $session = new PaymentCashierSessionEntity();
        $cashier = $this->createMock(PaymentCashierService::class);
        $cashier->expects($this->once())->method('create')->with($app, 'checkout', static::callback(static fn (array $data): bool => $data['profile'] === ' checkout '), static::callback(static fn (PaymentContext $context): bool => $context->currency === 'CNY'))->willReturn($session);
        $cashier->expects($this->once())->method('present')->with($app, $session)->willReturn(['id' => 'session']);
        $response = (new PaymentCashierController(PaymentControllerFixture::authenticator([$app]), $cashier, new PaymentContextParser()))->create($request);
        static::assertSame(['id' => 'session'], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    #[DataProvider('invalidMethods')]
    public function testRejectsAMissingOrBlankPaymentMethod(mixed $method): void
    {
        $app = PaymentControllerFixture::app('client', 'secret');
        $cashier = $this->createMock(PaymentCashierService::class);
        $cashier->expects($this->never())->method('pay');
        $request = PaymentControllerFixture::signedRequest('client', 'secret', 'POST', '/payment-api/v1/cashier-sessions/session/pay', ['method' => $method]);
        $this->expectExceptionObject(PaymentException::missingParameter('method'));
        (new PaymentCashierController(PaymentControllerFixture::authenticator([$app]), $cashier, new PaymentContextParser()))->pay($request, 'session');
    }

    public function testPaymentAndSessionReadsUseTheAuthenticatedApp(): void
    {
        $app = PaymentControllerFixture::app('client', 'secret');
        $session = new PaymentCashierSessionEntity();
        $cashier = $this->createMock(PaymentCashierService::class);
        $cashier->expects($this->once())->method('get')->with($app, 'session')->willReturn($session);
        $cashier->expects($this->once())->method('present')->with($app, $session)->willReturn(['id' => 'session']);
        $cashier->expects($this->once())->method('pay')->with($app, 'session', 'alipay')->willReturn(['handover' => 'redirect']);
        $controller = new PaymentCashierController(PaymentControllerFixture::authenticator([$app]), $cashier, new PaymentContextParser());
        $response = $controller->get(PaymentControllerFixture::signedRequest('client', 'secret', 'GET', '/payment-api/v1/cashier-sessions/session'), 'session');
        static::assertSame(['id' => 'session'], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
        $response = $controller->pay(PaymentControllerFixture::signedRequest('client', 'secret', 'POST', '/payment-api/v1/cashier-sessions/session/pay', ['method' => ' alipay ']), 'session');
        static::assertSame(['handover' => 'redirect'], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidMethods(): iterable
    {
        yield 'missing' => [null];
        yield 'blank' => ['  '];
        yield 'wrong type' => [42];
    }
}
