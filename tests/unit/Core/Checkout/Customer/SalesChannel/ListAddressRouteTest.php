<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\Event\AddressListingCriteriaEvent;
use Shopwell\Core\Checkout\Customer\SalesChannel\ListAddressRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ListAddressRoute::class)]
class ListAddressRouteTest extends TestCase
{
    /**
     * @var Stub&SalesChannelRepository<CustomerAddressCollection>
     */
    private Stub&SalesChannelRepository $addressRepository;

    private CollectingEventDispatcher $eventDispatcher;

    private ListAddressRoute $route;

    protected function setUp(): void
    {
        $this->addressRepository = static::createStub(SalesChannelRepository::class);
        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->route = new ListAddressRoute(
            $this->addressRepository,
            $this->eventDispatcher
        );
    }

    public function testGetDecoratedThrowsException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }

    public function testLoad(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();
        $customer = $context->getCustomer();
        static::assertNotNull($customer);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(
            new CustomerAddressCollection([$context->getShippingLocation()->getAddress() ?? new CustomerAddressEntity()])
        );

        /** @var MockObject&SalesChannelRepository<CustomerAddressCollection> $addressRepository */
        $addressRepository = $this->createMock(SalesChannelRepository::class);
        $addressRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) {
                    return $criteria->hasAssociation('salutation')
                        && $criteria->hasAssociation('country')
                        && $criteria->hasAssociation('countryState');
                }),
                $context
            )
            ->willReturn($searchResult);

        $route = new ListAddressRoute($addressRepository, $this->eventDispatcher);

        $response = $route->load($criteria, $context, $customer);

        static::assertCount(1, $response->getAddressCollection());

        $events = $this->eventDispatcher->getEvents();
        static::assertInstanceOf(AddressListingCriteriaEvent::class, $events[0]);
        static::assertSame($criteria, $events[0]->getCriteria());
        static::assertSame($context, $events[0]->getSalesChannelContext());
    }
}
