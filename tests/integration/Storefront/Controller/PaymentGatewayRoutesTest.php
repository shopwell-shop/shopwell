<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentGatewayRoutesTest extends TestCase
{
    use KernelTestBehaviour;

    public function testTheCheckoutHandOverRoutesAreRegistered(): void
    {
        $routes = static::getContainer()->get('router')->getRouteCollection();

        $form = $routes->get('frontend.payment.gateway.form');
        $qr = $routes->get('frontend.payment.gateway.qr');

        static::assertNotNull($form);
        static::assertNotNull($qr);
        static::assertSame(['GET'], $form->getMethods());
        static::assertSame(['GET'], $qr->getMethods());
        static::assertSame('/payment/gateway/{orderId}/{paymentNo}/form', $form->getPath());
        static::assertSame('/payment/gateway/{orderId}/{paymentNo}/qr', $qr->getPath());
    }
}
