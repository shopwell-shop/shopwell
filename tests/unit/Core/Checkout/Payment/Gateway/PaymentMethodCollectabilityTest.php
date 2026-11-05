<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodCollectability;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodCollectability::class)]
class PaymentMethodCollectabilityTest extends TestCase
{
    public function testUnavailableGatewayMethodIsHiddenWhileOtherHandlersRemain(): void
    {
        $binding = $this->binding();
        $repository = new StaticEntityRepository([new PaymentMethodGatewayChannelCollection([$binding])]);
        $routes = $this->createMock(PaymentRouteResolver::class);
        $context = new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'browser'));
        $routes->expects($this->once())->method('options')->with($binding->paymentApp, $context, [], ['plugin_method'], 'priority')->willReturn([]);
        $methods = new PaymentMethodCollection([
            (new PaymentMethodEntity())->assign(['id' => 'gateway', 'handlerIdentifier' => PaymentGatewayHandler::class]),
            (new PaymentMethodEntity())->assign(['id' => 'invoice', 'handlerIdentifier' => 'invoice-handler']),
        ]);

        $visible = (new PaymentMethodCollectability($repository, $routes))->filter($methods, Context::createDefaultContext(), $context);

        static::assertSame(['invoice'], array_values($visible->getIds()));
        static::assertArrayHasKey('paymentApp', $repository->searchedCriteria[0]->getAssociations());
    }

    public function testNonGatewayMethodsDoNotTriggerAnyRouteLookup(): void
    {
        $repository = StaticEntityRepository::of(PaymentMethodGatewayChannelCollection::class);
        $routes = $this->createMock(PaymentRouteResolver::class);
        $routes->expects($this->never())->method('options');
        $methods = new PaymentMethodCollection([(new PaymentMethodEntity())->assign(['id' => 'invoice', 'handlerIdentifier' => 'invoice-handler'])]);

        static::assertSame($methods, (new PaymentMethodCollectability($repository, $routes))->filter($methods, Context::createDefaultContext(), new PaymentContext('CNY', new PaymentScene('browser', 'web', 'browser'))));
    }

    private function binding(): PaymentMethodGatewayChannelEntity
    {
        $binding = (new PaymentMethodGatewayChannelEntity())->assign(['id' => 'binding', 'paymentMethodId' => 'gateway', 'paymentAppId' => 'app', 'methodTechnicalName' => 'plugin_method']);
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentApp = new PaymentAppEntity();

        return $binding;
    }
}
