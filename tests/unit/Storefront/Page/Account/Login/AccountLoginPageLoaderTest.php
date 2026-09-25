<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Login;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Country\CountryDefinition;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\Country\SalesChannel\CountryRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopwell\Core\System\Salutation\SalesChannel\SalutationRouteResponse;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Shopwell\Core\System\Salutation\SalutationDefinition;
use Shopwell\Core\System\Salutation\SalutationEntity;
use Shopwell\Core\System\Salutation\SalutationSorter;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPage;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPageLoadedEvent;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountLoginPageLoader::class)]
class AccountLoginPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private CountryRoute&Stub $countryRoute;

    private SalutationRoute&Stub $salutationRoute;

    private SalutationSorter&Stub $salutationSorter;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->countryRoute = static::createStub(CountryRoute::class);
        $this->salutationRoute = static::createStub(SalutationRoute::class);
        $this->salutationSorter = static::createStub(SalutationSorter::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericLoader = static::createStub(GenericPageLoader::class);
    }

    public function testLoad(): void
    {
        $country = new CountryEntity();
        $country->assign(
            [
                'id' => Uuid::randomHex(),
                'name' => 'lalaland',
            ]
        );
        $country->setUniqueIdentifier(Uuid::randomHex());
        $countries = new CountryCollection([$country]);
        $countryResponse = new CountryRouteResponse(
            new EntitySearchResult(
                CountryDefinition::ENTITY_NAME,
                1,
                $countries,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $countryRoute = $this->createMock(CountryRoute::class);
        $countryRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($countryResponse);

        $salutation = new SalutationEntity();
        $salutation->setId(Uuid::randomHex());

        $salutation2Id = Uuid::randomHex();
        $salutation2 = new SalutationEntity();
        $salutation2->setId($salutation2Id);

        $salutations = new SalutationCollection([$salutation, $salutation2]);
        $salutationResponse = new SalutationRouteResponse(
            new EntitySearchResult(
                SalutationDefinition::ENTITY_NAME,
                2,
                $salutations,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $salutationsSorted = new SalutationCollection([$salutation2, $salutation]);

        $salutationRoute = $this->createMock(SalutationRoute::class);
        $salutationRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($salutationResponse);

        $salutationSorter = $this->createMock(SalutationSorter::class);
        $salutationSorter
            ->expects($this->once())
            ->method('sort')
            ->willReturn($salutationsSorted);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');
        $genericLoader = $this->createMock(GenericPageLoader::class);
        $genericLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $pageLoader = new AccountLoginPageLoader(
            $genericLoader,
            $this->eventDispatcher,
            $countryRoute,
            $salutationRoute,
            $salutationSorter,
            $translator
        );

        $page = $pageLoader->load(new Request(), static::createStub(SalesChannelContext::class));

        static::assertSame($countries, $page->getCountries());
        static::assertSame($salutationsSorted, $page->getSalutations());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());
        $events = $this->eventDispatcher->getEvents();

        static::assertCount(1, $events);
        static::assertInstanceOf(AccountLoginPageLoadedEvent::class, $events[0]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountLoginPageLoader(
            $this->genericLoader,
            $this->eventDispatcher,
            $this->countryRoute,
            $this->salutationRoute,
            $this->salutationSorter,
            $this->translator
        );

        $page = new AccountLoginPage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }
}

/**
 * @internal
 */
class TestAccountLoginPageLoader extends AccountLoginPageLoader
{
    public function setMetaInformationAccess(AccountLoginPage $page): void
    {
        self::setMetaInformation($page);
    }
}
