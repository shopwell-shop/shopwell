<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Checkout\Offcanvas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopwell\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopwell\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Shopwell\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopwell\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OffcanvasCartPageLoader::class)]
class OffcanvasCartPageLoaderTest extends TestCase
{
    public function testOffcanvasCartPageReturned(): void
    {
        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn(new Page());

        $this->expectNotToPerformAssertions();

        $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new OffcanvasCartPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            static::createStub(SalesChannelContext::class)
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new OffcanvasCartPage();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            static::createStub(SalesChannelContext::class)
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testShippingMethodsAreSetToPage(): void
    {
        $shippingMethods = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $shippingMethodResponse = new ShippingMethodRouteResponse(
            new EntitySearchResult(
                ShippingMethodDefinition::ENTITY_NAME,
                2,
                $shippingMethods,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $shippingMethodRoute = static::createStub(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->method('load')
            ->willReturn($shippingMethodResponse);

        $page = $this->createLoader(shippingMethodRoute: $shippingMethodRoute)->load(
            new Request(),
            static::createStub(SalesChannelContext::class)
        );

        static::assertSame($shippingMethods, $page->getShippingMethods());
    }

    public function testValidationEventIsDispatched(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(OffcanvasCartPageLoadedEvent::class));

        $this->createLoader(eventDispatcher: $eventDispatcher)->load(
            new Request(),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testOnlyAvailableFlagIsSet(): void
    {
        $request = new Request(['onlyAvailable' => true]);
        $context = Generator::generateSalesChannelContext();

        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->expects($this->once())
            ->method('load')
            ->with($request, $context, static::equalTo(new Criteria()));

        $loader = $this->createLoader(shippingMethodRoute: $shippingMethodRoute);
        $loader->load(new Request(), $context);
    }

    private function createLoader(
        ?EventDispatcher $eventDispatcher = null,
        ?GenericPageLoader $pageLoader = null,
        ?ShippingMethodRoute $shippingMethodRoute = null,
    ): OffcanvasCartPageLoader {
        return new OffcanvasCartPageLoader(
            $eventDispatcher ?? static::createStub(EventDispatcher::class),
            static::createStub(StorefrontCartFacade::class),
            $pageLoader ?? static::createStub(GenericPageLoader::class),
            $shippingMethodRoute ?? static::createStub(ShippingMethodRoute::class),
        );
    }
}
