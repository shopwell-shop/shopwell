<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnGuestRoute;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRouteResponse;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPageLoadedEvent;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPageLoader;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A token that expired or was forged is not an error a guest is told about: the page falls back to
 * the verification form, which is what the guest can act on.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(GuestOrderReturnPageLoader::class)]
class GuestOrderReturnPageLoaderTest extends TestCase
{
    public function testWithoutATokenThePageOnlyOffersTheForm(): void
    {
        $page = $this->loader()->load(new Request(), Generator::generateSalesChannelContext(), null);

        static::assertFalse($page->isVerified());
        static::assertSame([], $page->getReturns());
    }

    public function testTheTokenIsHandedToTheRouteAndThePageBecomesVerified(): void
    {
        $route = $this->createMock(OrderReturnGuestRoute::class);
        $route->expects($this->once())
            ->method('load')
            ->with(static::callback(static fn (Request $request): bool => $request->query->get('token') === 'the-token'))
            ->willReturn($this->response([
                'orderNumber' => 'SW-10001',
                'orderReturns' => [['id' => 'return-id']],
                'positions' => [['lineItemId' => 'line-item-id', 'quantity' => 1]],
                'reasons' => [['id' => 'reason-id', 'label' => 'broken']],
            ]));

        $request = new Request();
        $page = $this->loader($route)->load($request, Generator::generateSalesChannelContext(), 'the-token');

        static::assertTrue($page->isVerified());
        static::assertSame('SW-10001', $page->getOrderNumber());
        static::assertSame([['id' => 'return-id']], $page->getReturns());
        static::assertSame([['lineItemId' => 'line-item-id', 'quantity' => 1]], $page->getPositions());
        static::assertSame([['id' => 'reason-id', 'label' => 'broken']], $page->getReasons());

        // the request the storefront renders is not changed by the loader
        static::assertNull($request->query->get('token'));
    }

    public function testATokenTheRouteRefusesLeadsBackToTheForm(): void
    {
        $route = static::createStub(OrderReturnGuestRoute::class);
        $route->method('load')->willThrowException(OrderReturnException::guestAccessDenied());

        $page = $this->loader($route)->load(new Request(), Generator::generateSalesChannelContext(), 'a-forged-token');

        static::assertFalse($page->isVerified());
        static::assertSame([], $page->getReturns());
    }

    public function testTheDetailOfAGuestIsVerified(): void
    {
        $route = static::createStub(OrderReturnGuestRoute::class);
        $route->method('detail')->willReturn($this->response(['orderReturn' => ['id' => 'return-id']]));

        $page = $this->loader($route)->loadDetail(new Request(), Generator::generateSalesChannelContext(), 'the-token', 'return-id');

        static::assertTrue($page->isVerified());
        static::assertSame(['id' => 'return-id'], $page->getCurrent());
    }

    public function testTheVerificationFormIsAnnouncedAsAPageLoad(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(GuestOrderReturnPageLoadedEvent::class));

        $page = $this->loader(dispatcher: $eventDispatcher)->loadForm(new Request(), Generator::generateSalesChannelContext());

        static::assertFalse($page->isVerified());
        $meta = $page->getMetaInformation();
        static::assertInstanceOf(MetaInformation::class, $meta);
        static::assertSame('noindex,nofollow', $meta->getRobots());
    }

    private function loader(
        ?OrderReturnGuestRoute $route = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): GuestOrderReturnPageLoader {
        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturn(new Page());

        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        return new GuestOrderReturnPageLoader(
            $genericLoader,
            $dispatcher ?? static::createStub(EventDispatcherInterface::class),
            $route ?? static::createStub(OrderReturnGuestRoute::class),
            $translator,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function response(array $data): OrderReturnRouteResponse
    {
        return new OrderReturnRouteResponse(new ArrayStruct($data));
    }
}
