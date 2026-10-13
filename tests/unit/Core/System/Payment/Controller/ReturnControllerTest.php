<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Controller\ReturnController;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Service\PaymentConfigResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReturnController::class)]
class ReturnControllerTest extends TestCase
{
    public function testAnUnknownPaymentOrderIsNotFound(): void
    {
        $controller = new ReturnController(
            PaymentControllerFixture::orderService(PaymentControllerFixture::repository(PaymentOrderCollection::class), $this->resolver()),
            PaymentControllerFixture::presenter()
        );

        $response = $controller->redirectToApp('PG-unknown');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testAnOrderWithoutReturnUrlIsNotFound(): void
    {
        $response = $this->controllerFor(PaymentControllerFixture::order('app-1', ['returnUrl' => null]))->redirectToApp('PG-1');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testABlankReturnUrlIsNotFound(): void
    {
        $response = $this->controllerFor(PaymentControllerFixture::order('app-1', ['returnUrl' => '']))->redirectToApp('PG-1');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testAReturnUrlThatIsNotHttpIsNotFound(): void
    {
        $response = $this->controllerFor(PaymentControllerFixture::order('app-1', ['returnUrl' => 'javascript:alert(1)']))->redirectToApp('PG-1');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testAReturnUrlWithoutAHostIsNotFound(): void
    {
        $response = $this->controllerFor(PaymentControllerFixture::order('app-1', ['returnUrl' => 'http:///path']))->redirectToApp('PG-1');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testThePayerIsRedirectedToTheAppTogetherWithTheState(): void
    {
        $controller = $this->controllerFor(PaymentControllerFixture::order('app-1', ['returnUrl' => 'https://app.example.com/return']), ['paid']);

        $response = $controller->redirectToApp('PG-1');

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame(
            'https://app.example.com/return?payment_no=PG-1&out_trade_no=T-1&state=paid',
            $response->getTargetUrl()
        );
    }

    public function testAnExistingQueryOfTheAppIsKept(): void
    {
        $controller = $this->controllerFor(PaymentControllerFixture::order('app-1', ['returnUrl' => 'https://app.example.com/return?lang=de']), ['opened']);

        $response = $controller->redirectToApp('PG-1');

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(
            'https://app.example.com/return?lang=de&payment_no=PG-1&out_trade_no=T-1&state=opened',
            $response->getTargetUrl()
        );
    }

    public function testTheStateIsOmittedWhenThePresenterCannotResolveIt(): void
    {
        $controller = $this->controllerFor(PaymentControllerFixture::order('app-1', ['returnUrl' => 'https://app.example.com/return']));

        $response = $controller->redirectToApp('PG-1');

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(
            'https://app.example.com/return?payment_no=PG-1&out_trade_no=T-1',
            $response->getTargetUrl()
        );
    }

    /**
     * @param list<string> $stateNames
     */
    private function controllerFor(PaymentOrderEntity $order, array $stateNames = []): ReturnController
    {
        $states = array_map(PaymentControllerFixture::stateName(...), $stateNames);

        return new ReturnController(
            PaymentControllerFixture::orderService(PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]), $this->resolver()),
            PaymentControllerFixture::presenter($states)
        );
    }

    private function resolver(): PaymentConfigResolver
    {
        $providers = PaymentControllerFixture::providerRegistry();

        return PaymentControllerFixture::configResolver($providers, PaymentControllerFixture::channelRegistry($providers));
    }
}
