<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRoute;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRouteResponse;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPageLoadedEvent;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPageLoader;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The loader is the only place that turns the answers of the store api into the page, so what a
 * template of "my returns" may read is decided here.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AccountOrderReturnPageLoader::class)]
class AccountOrderReturnPageLoaderTest extends TestCase
{
    public function testTheListIsReadFromTheRouteAndThePageIsAnnounced(): void
    {
        $returns = [['id' => 'return-id', 'returnNumber' => 'RT-1']];

        $route = $this->createMock(OrderReturnRoute::class);
        $route->expects($this->once())
            ->method('load')
            ->willReturn($this->response(['orderReturns' => $returns]));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(AccountOrderReturnPageLoadedEvent::class));

        $page = $this->loader($route, $eventDispatcher)
            ->loadList(new Request(), Generator::generateSalesChannelContext(), $this->customer());

        static::assertSame($returns, $page->getReturns());
        static::assertSame([], $page->getPositions());
        static::assertNull($page->getCurrent());
    }

    public function testTheDetailIsTheOnlyReturnOnThePage(): void
    {
        $return = ['id' => 'return-id', 'returnNumber' => 'RT-1'];

        $route = $this->createMock(OrderReturnRoute::class);
        $route->expects($this->once())
            ->method('detail')
            ->with(static::isInstanceOf(Request::class), 'return-id')
            ->willReturn($this->response(['orderReturn' => $return]));

        $page = $this->loader($route)->loadDetail(
            new Request(),
            Generator::generateSalesChannelContext(),
            $this->customer(),
            'return-id'
        );

        static::assertSame($return, $page->getCurrent());
        static::assertSame([], $page->getReturns());
    }

    public function testTheCreateFormOffersThePositionsAndTheReasons(): void
    {
        $route = $this->createMock(OrderReturnRoute::class);
        $route->expects($this->once())
            ->method('positions')
            ->with(static::isInstanceOf(Request::class), 'order-id')
            ->willReturn($this->response([
                'orderNumber' => 'SW-10001',
                'positions' => [['lineItemId' => 'line-item-id', 'quantity' => 2]],
                'reasons' => [['id' => 'reason-id', 'label' => 'broken']],
            ]));

        $page = $this->loader($route)->loadCreate(
            new Request(),
            Generator::generateSalesChannelContext(),
            $this->customer(),
            'order-id'
        );

        static::assertSame('SW-10001', $page->getOrderNumber());
        static::assertSame([['lineItemId' => 'line-item-id', 'quantity' => 2]], $page->getPositions());
        static::assertSame([['id' => 'reason-id', 'label' => 'broken']], $page->getReasons());
    }

    public function testThePagesOfACustomerAreNotIndexed(): void
    {
        $route = static::createStub(OrderReturnRoute::class);
        $route->method('load')->willReturn($this->response(['orderReturns' => []]));

        $page = $this->loader($route)->loadList(
            new Request(),
            Generator::generateSalesChannelContext(),
            $this->customer()
        );

        $meta = $page->getMetaInformation();
        static::assertInstanceOf(MetaInformation::class, $meta);
        static::assertSame('account.orderReturnMetaTitle | ', $meta->getMetaTitle());
        static::assertSame('noindex,follow', $meta->getRobots());
    }

    private function loader(OrderReturnRoute $route, ?EventDispatcherInterface $eventDispatcher = null): AccountOrderReturnPageLoader
    {
        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturn(new Page());

        return new AccountOrderReturnPageLoader(
            $genericLoader,
            $eventDispatcher ?? static::createStub(EventDispatcherInterface::class),
            $route,
            $this->translator(),
        );
    }

    private function translator(): AbstractTranslator
    {
        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        return $translator;
    }

    private function customer(): CustomerEntity
    {
        return (new CustomerEntity())->assign(['id' => 'customer-id']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function response(array $data): OrderReturnRouteResponse
    {
        return new OrderReturnRouteResponse(new ArrayStruct($data));
    }
}
