<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopwell\Storefront\Checkout\Order\OrderProductAvailabilityResolver;
use Shopwell\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopwell\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Shopwell\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopwell\Storefront\Page\Account\Order\AccountOrderStateCounter;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountOrderPageLoader::class)]
class AccountOrderPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private OrderRoute&MockObject $orderRoute;

    private AccountOrderPageLoader $pageLoader;

    private AbstractTranslator&MockObject $translator;

    private GenericPageLoader&MockObject $genericPageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = $this->createMock(OrderRoute::class);
        $this->translator = $this->createMock(AbstractTranslator::class);
        $this->genericPageLoader = $this->createMock(GenericPageLoader::class);

        $this->pageLoader = new AccountOrderPageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->orderRoute,
            $this->translator,
            static::createStub(OrderProductAvailabilityResolver::class),
            // The counts go through their own route stub so the order-list route stays a `once()` expectation.
            // They also get their own dispatcher: the counter fires one `OrderRouteRequestEvent` per state
            // bucket, which would otherwise drown the page-level event assertions below. That the counter
            // dispatches its events is covered by AccountOrderStateCounterTest.
            new AccountOrderStateCounter(static::createStub(OrderRoute::class), static::createStub(EventDispatcherInterface::class)),
        );
    }

    public function testLoadWithGuestLogin(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $orders = new OrderCollection([$order]);

        $orderResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $context = Generator::generateSalesChannelContext();

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static fn (Request $request) => $request->query->get('email') === 'test@example.com' && $request->query->get('zipcode') === '12345' && $request->query->get('login') === true),
                $context,
                static::isInstanceOf(Criteria::class),
            )
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $this->genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $page = $this->pageLoader->load(new Request(['email' => 'test@example.com', 'zipcode' => '12345']), $context);

        static::assertSame($order, $page->getOrders()->getEntities()->first());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();

        // one event for the order list, the tag counts run through the counter, the page event closes
        $orderRouteEvents = array_values(array_filter(
            $events,
            static fn (object $event): bool => $event instanceof OrderRouteRequestEvent
        ));
        static::assertGreaterThan(0, \count($orderRouteEvents));
        $lastEvent = $orderRouteEvents === [] ? null : $events[\count($events) - 1];
        static::assertInstanceOf(AccountOrderPageLoadedEvent::class, $lastEvent);

        // the tag row got a number for the unfiltered list and for every filter, from the counter
        static::assertSame(
            array_merge([AccountOrderStateCounter::FILTER_ALL], array_keys(AccountOrderStateCounter::FILTERS)),
            array_keys($page->getOrderStateCounts())
        );
        static::assertSame(AccountOrderStateCounter::FILTER_ALL, $page->getOrderStateFilter());
    }

    public function testAKnownOrderStateBecomesAFilterOnTheOrderList(): void
    {
        $capturedCriteria = null;

        $this->expectPageToLoad();

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (Request $request, SalesChannelContext $context, Criteria $criteria) use (&$capturedCriteria) {
                $capturedCriteria = $criteria;

                return $this->orderResponse();
            });

        $context = Generator::generateSalesChannelContext();
        $page = $this->pageLoader->load(new Request(['orderState' => 'pending_payment']), $context);

        static::assertSame('pending_payment', $page->getOrderStateFilter());
        static::assertNotNull($capturedCriteria);

        $filters = $capturedCriteria->getFilters();
        static::assertCount(1, $filters);
        static::assertInstanceOf(EqualsFilter::class, $filters[0]);
        static::assertSame('stateMachineState.technicalName', $filters[0]->getField());
        static::assertSame('open', $filters[0]->getValue());
    }

    public function testAnUnknownOrderStateFallsBackToTheUnfilteredList(): void
    {
        $capturedCriteria = null;

        $this->expectPageToLoad();

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (Request $request, SalesChannelContext $context, Criteria $criteria) use (&$capturedCriteria) {
                $capturedCriteria = $criteria;

                return $this->orderResponse();
            });

        $context = Generator::generateSalesChannelContext();
        $page = $this->pageLoader->load(new Request(['orderState' => 'something-made-up']), $context);

        // a stale bookmark shows the orders instead of erroring
        static::assertSame(AccountOrderStateCounter::FILTER_ALL, $page->getOrderStateFilter());
        static::assertNotNull($capturedCriteria);
        static::assertSame([], $capturedCriteria->getFilters());
    }

    #[DataProvider('pageParameterProvider')]
    public function testLoadUsesNonNegativeOffsetForPageParameter(string $pageParameter, int $expectedOffset): void
    {
        $context = Generator::generateSalesChannelContext();

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::isInstanceOf(Request::class),
                $context,
                static::callback(static fn (Criteria $criteria) => $criteria->getOffset() === $expectedOffset),
            )
            ->willReturn(new OrderRouteResponse(
                new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    0,
                    new OrderCollection(),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            ));

        $this->genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new Page());

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $this->pageLoader->load(new Request(['p' => $pageParameter]), $context);
    }

    public static function pageParameterProvider(): \Generator
    {
        yield 'negative page falls back to the first page' => ['-5', 0];
        yield 'zero page falls back to the first page' => ['0', 0];
        yield 'non-numeric page falls back to the first page' => ['foo', 0];
        yield 'valid page is converted to its offset' => ['3', 20];
    }

    /**
     * Every test has to set an expectation on the shared page-loader double, otherwise PHPUnit reports
     * the "no expectations" notice for a mock that other tests do use with expectations.
     */
    private function expectPageToLoad(): void
    {
        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $this->genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');
    }

    private function orderResponse(): OrderRouteResponse
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        return new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                new OrderCollection([$order]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
    }
}
