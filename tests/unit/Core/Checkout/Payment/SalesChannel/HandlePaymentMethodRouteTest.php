<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Extension\HandlePaymentMethodRouteExtension;
use Shopwell\Core\Checkout\Payment\PaymentProcessor;
use Shopwell\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopwell\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRouteResponse;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(HandlePaymentMethodRoute::class)]
class HandlePaymentMethodRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $response = new HandlePaymentMethodRouteResponse(null);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('handle-payment-method-route.load.pre', static function (HandlePaymentMethodRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new HandlePaymentMethodRoute(
            static::createStub(PaymentProcessor::class),
            static::createStub(DataValidator::class),
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context));
    }
}
