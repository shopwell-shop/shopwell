<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryEntity;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRoute;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopwell\Storefront\Event\RouteRequest\DictionaryRouteRequestEvent;
use Shopwell\Storefront\Page\Account\Profile\AccountProfilePage;
use Shopwell\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Shopwell\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountProfilePageLoader::class)]
class AccountProfilePageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private AccountProfilePageLoader $pageLoader;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    private DictionaryRoute&Stub $genderRoute;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->genderRoute = static::createStub(DictionaryRoute::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);

        $this->pageLoader = new AccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->genderRoute,
            $this->translator
        );
    }

    public function testLoad(): void
    {
        $gender = new DictionaryEntryEntity();
        $gender->setId(Uuid::randomHex());

        $gender2Id = Uuid::randomHex();
        $gender2 = new DictionaryEntryEntity();
        $gender2->setId($gender2Id);

        $gendersSorted = new DictionaryEntryCollection([$gender2, $gender]);

        $genderRoute = $this->createMock(DictionaryRoute::class);
        $genderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn(new DictionaryRouteResponse(
                new EntitySearchResult(
                    DictionaryEntryDefinition::ENTITY_NAME,
                    2,
                    $gendersSorted,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            ));

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');
        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $pageLoader = new AccountProfilePageLoader(
            $genericPageLoader,
            $this->eventDispatcher,
            $genderRoute,
            $translator
        );

        $salesChannelContext = $this->getContextWithDummyCustomer();
        $page = $pageLoader->load(new Request(), $salesChannelContext);

        static::assertSame($gendersSorted, $page->getGenders());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);

        static::assertInstanceOf(AccountProfilePageLoadedEvent::class, $events[1]);
        static::assertInstanceOf(DictionaryRouteRequestEvent::class, $events[0]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->genderRoute,
            $this->translator,
        );

        $page = new AccountProfilePage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    public function testNoCustomerException(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        static::expectException(CustomerNotLoggedInException::class);

        $this->pageLoader->load(new Request(), $salesChannelContext);
    }

    private function getContextWithDummyCustomer(): SalesChannelContext
    {
        $customer = new CustomerEntity();

        $context = static::createStub(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn($customer);

        return $context;
    }
}

/**
 * @internal
 */
class TestAccountProfilePageLoader extends AccountProfilePageLoader
{
    public function setMetaInformationAccess(AccountProfilePage $page): void
    {
        self::setMetaInformation($page);
    }
}
