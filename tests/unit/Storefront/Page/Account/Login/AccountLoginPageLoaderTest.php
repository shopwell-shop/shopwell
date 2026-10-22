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
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryEntity;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRoute;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
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

    private DictionaryRoute&Stub $genderRoute;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->countryRoute = static::createStub(CountryRoute::class);
        $this->genderRoute = static::createStub(DictionaryRoute::class);
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
            $genderRoute,
            $translator
        );

        $page = $pageLoader->load(new Request(), static::createStub(SalesChannelContext::class));

        static::assertSame($countries, $page->getCountries());
        static::assertSame($gendersSorted, $page->getGenders());
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
            $this->genderRoute,
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
