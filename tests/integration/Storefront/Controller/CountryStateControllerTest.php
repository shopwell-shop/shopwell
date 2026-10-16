<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Script\Debugging\ScriptTraces;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Controller\CountryStateController;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPagelet;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPageletCriteriaEvent;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPageletLoadedEvent;
use Shopwell\Storefront\Pagelet\Country\CountryStateDataPagelet;
use Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletCriteriaEvent;
use Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletLoadedEvent;
use Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletLoadedHook;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class CountryStateControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private Connection $connection;

    private string $countryIdDE;

    private string $countryIdAT;

    private CountryStateController $countryStateController;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $this->countryIdDE = $this->getCountryIdByIso();
        $this->countryIdAT = $this->getCountryIdByIso('AT');

        $this->countryStateController = static::getContainer()->get(CountryStateController::class);

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testGetCountryData(): void
    {
        $response = $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->salesChannelContext);

        static::assertCount(16, \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['states']);
    }

    public function testGetCountryDataFromQueryParameter(): void
    {
        $response = $this->countryStateController->getCountryData(new Request(['countryId' => $this->countryIdAT]), $this->salesChannelContext);

        static::assertCount(0, \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['states']);
    }

    public function testGetCountryDataPrefersQueryParameterOverPostData(): void
    {
        $response = $this->countryStateController->getCountryData(
            new Request(['countryId' => $this->countryIdDE], ['countryId' => $this->countryIdAT]),
            $this->salesChannelContext
        );

        $states = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['states'];

        static::assertCount(16, $states);
    }

    public function testEmptyCountryId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryId'));
        $this->countryStateController->getCountryData(new Request(), $this->salesChannelContext);
    }

    public function testGetCityData(): void
    {
        $response = $this->countryStateController->getCityData(
            new Request(['countryStateId' => $this->getCountryStateIdByShortCode('CN-11')]),
            $this->salesChannelContext
        );

        $cities = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['cities'];

        static::assertNotSame([], $cities);
        static::assertArrayHasKey('id', $cities[0]);
        static::assertArrayHasKey('translated', $cities[0]);
        static::assertNotSame('', $cities[0]['translated']['name']);
    }

    public function testGetCityDataWithoutCountryStateId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryStateId'));
        $this->countryStateController->getCityData(new Request(), $this->salesChannelContext);
    }

    public function testGetDistrictData(): void
    {
        $cityId = $this->getCountryCityIdOfState($this->getCountryStateIdByShortCode('CN-11'));

        $response = $this->countryStateController->getDistrictData(
            new Request(['countryCityId' => $cityId]),
            $this->salesChannelContext
        );

        $districts = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['districts'];

        static::assertNotSame([], $districts);
        static::assertArrayHasKey('id', $districts[0]);
        static::assertArrayHasKey('translated', $districts[0]);
        static::assertNotSame('', $districts[0]['translated']['name']);
    }

    public function testGetDistrictDataWithoutCountryCityId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryCityId'));
        $this->countryStateController->getDistrictData(new Request(), $this->salesChannelContext);
    }

    public function testCountryStateControllerEvents(): void
    {
        $testSubscriber = new CountryStateControllerTestSubscriber();
        EventHookDispatcher::fromContainer(static::getContainer())->subscribe($testSubscriber);

        $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->salesChannelContext);

        static::assertInstanceOf(CountryStateDataPagelet::class, $testSubscriber->testPagelet);
        static::assertInstanceOf(CountryStateDataPageletCriteriaEvent::class, $testSubscriber->criteriaEvent);
    }

    public function testCountryStateControllerHooks(): void
    {
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        static::getContainer()->get('app.repository')->create([[
            'id' => $appId,
            'name' => 'Test',
            'path' => __DIR__ . '/fixtures/Apps/storefront-endpoint-cases',
            'active' => true,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'id' => $integrationId,
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'Test',
            ],
            'scripts' => [
                [
                    'name' => 'country-loaded/loaded.script.twig',
                    'hook' => 'country-state-data-pagelet-loaded',
                    'script' => '{% do debug.dump(hook.getPage.getStates.count) %}',
                    'active' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $request = new Request([], ['countryId' => $this->countryIdDE]);
        static::getContainer()->get('request_stack')->push($request);

        $this->countryStateController->getCountryData($request, $this->salesChannelContext);

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CountryStateDataPageletLoadedHook::HOOK_NAME, $traces);

        static::assertSame([16], $traces['country-state-data-pagelet-loaded'][0]['output']);
    }

    public function testCountryRegionDataControllerEvents(): void
    {
        $testSubscriber = new CountryStateControllerTestSubscriber();
        EventHookDispatcher::fromContainer(static::getContainer())->subscribe($testSubscriber);

        $this->countryStateController->getCityData(
            new Request(['countryStateId' => $this->getCountryStateIdByShortCode('CN-11')]),
            $this->salesChannelContext
        );

        static::assertInstanceOf(CountryRegionDataPagelet::class, $testSubscriber->testRegionPagelet);
        static::assertInstanceOf(CountryRegionDataPageletCriteriaEvent::class, $testSubscriber->regionCriteriaEvent);
    }

    private function getCountryIdByIso(string $iso = 'DE'): string
    {
        $countryId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM country WHERE iso = :iso', ['iso' => $iso]);
        static::assertIsString($countryId);

        return $countryId;
    }

    private function getCountryStateIdByShortCode(string $shortCode): string
    {
        $countryStateId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM country_state WHERE short_code = :shortCode',
            ['shortCode' => $shortCode]
        );
        static::assertIsString($countryStateId);

        return $countryStateId;
    }

    private function getCountryCityIdOfState(string $countryStateId): string
    {
        $countryCityId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM country_city WHERE country_state_id = UNHEX(:countryStateId) ORDER BY position LIMIT 1',
            ['countryStateId' => $countryStateId]
        );
        static::assertIsString($countryCityId);

        return $countryCityId;
    }
}

/**
 * @internal
 */
class CountryStateControllerTestSubscriber implements EventSubscriberInterface
{
    public ?CountryStateDataPagelet $testPagelet = null;

    public ?CountryStateDataPageletCriteriaEvent $criteriaEvent = null;

    public ?CountryRegionDataPagelet $testRegionPagelet = null;

    public ?CountryRegionDataPageletCriteriaEvent $regionCriteriaEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            CountryStateDataPageletLoadedEvent::class => 'onPageletLoaded',
            CountryStateDataPageletCriteriaEvent::class => 'onCriteria',
            CountryRegionDataPageletLoadedEvent::class => 'onRegionPageletLoaded',
            CountryRegionDataPageletCriteriaEvent::class => 'onRegionCriteria',
        ];
    }

    public function onPageletLoaded(CountryStateDataPageletLoadedEvent $event): void
    {
        $this->testPagelet = $event->getPagelet();
    }

    public function onCriteria(CountryStateDataPageletCriteriaEvent $event): void
    {
        $this->criteriaEvent = $event;
    }

    public function onRegionPageletLoaded(CountryRegionDataPageletLoadedEvent $event): void
    {
        $this->testRegionPagelet = $event->getPagelet();
    }

    public function onRegionCriteria(CountryRegionDataPageletCriteriaEvent $event): void
    {
        $this->regionCriteriaEvent = $event;
    }
}
