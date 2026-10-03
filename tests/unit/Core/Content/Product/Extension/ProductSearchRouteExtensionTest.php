<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Examples\ProductSearchRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductSearchRouteExtension::class)]
class ProductSearchRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesSearch(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ProductSearchRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ProductSearchRouteExtension::NAME,
            extension: new ProductSearchRouteExtension(new Request(), $context, $criteria),
            function: static function () use (&$coreCalled): ProductSearchRouteResponse {
                $coreCalled = true;

                return new ProductSearchRouteResponse(new ProductListingResult(
                    ProductDefinition::ENTITY_NAME,
                    0,
                    new ProductCollection(),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ));
            },
        );

        static::assertFalse($coreCalled, 'The core product search must be skipped when a subscriber resolves it.');
        static::assertSame($criteria, $result->getListingResult()->getCriteria());
    }
}
