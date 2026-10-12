<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Feature\FeatureException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\CountryStateController;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPagelet;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPageletLoader;
use Shopwell\Storefront\Pagelet\Country\CountryStateDataPagelet;
use Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Shopwell\Tests\Unit\Storefront\Controller\Stub\CountryStateControllerStub;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryStateController::class)]
class CountryStateControllerTest extends TestCase
{
    private CountryStateDataPageletLoader&MockObject $pageletLoader;

    private CountryRegionDataPageletLoader&MockObject $regionPageletLoader;

    private CountryStateControllerStub $controller;

    protected function setUp(): void
    {
        $this->pageletLoader = static::createMock(CountryStateDataPageletLoader::class);
        $this->regionPageletLoader = static::createMock(CountryRegionDataPageletLoader::class);
        $this->controller = new CountryStateControllerStub($this->pageletLoader, $this->regionPageletLoader);
    }

    public function testGetCountryDataUsesCountryIdFromQuery(): void
    {
        $request = new Request(['countryId' => 'query-country-id']);
        $context = Generator::generateSalesChannelContext();

        $this->pageletLoader->expects($this->once())
            ->method('load')
            ->with('query-country-id', $request, $context)
            ->willReturn(new CountryStateDataPagelet());
        $this->regionPageletLoader->expects($this->never())
            ->method('loadCities');

        $this->controller->getCountryData($request, $context);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetCountryDataFallsBackToCountryIdFromPost(): void
    {
        $request = Request::create('/country/country-state-data', Request::METHOD_POST, ['countryId' => 'post-country-id']);
        $context = Generator::generateSalesChannelContext();

        $this->pageletLoader->expects($this->once())
            ->method('load')
            ->with('post-country-id', $request, $context)
            ->willReturn(new CountryStateDataPagelet());
        $this->regionPageletLoader->expects($this->never())
            ->method('loadCities');

        $this->controller->getCountryData($request, $context);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove when the v6.8.0.0 feature flag is removed
     */
    public function testGetCountryDataThrowsForPostRequestsWhenV6800IsActive(): void
    {
        $this->pageletLoader->expects($this->never())
            ->method('load');
        $this->regionPageletLoader->expects($this->never())
            ->method('loadCities');

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: The POST request to /country/country-state-data is deprecated and will be removed in v6.8.0.0. Use a GET request instead.'
        ));

        $this->controller->getCountryData(
            Request::create('/country/country-state-data', Request::METHOD_POST, ['countryId' => 'post-country-id']),
            Generator::generateSalesChannelContext()
        );
    }

    public function testGetCountryDataThrowsExceptionWithoutCountryId(): void
    {
        $this->pageletLoader->expects($this->never())
            ->method('load');
        $this->regionPageletLoader->expects($this->never())
            ->method('loadCities');

        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryId'));

        $this->controller->getCountryData(new Request(), Generator::generateSalesChannelContext());
    }

    public function testGetCityDataUsesCountryStateIdFromQuery(): void
    {
        $request = new Request(['countryStateId' => 'query-state-id']);
        $context = Generator::generateSalesChannelContext();

        $this->pageletLoader->expects($this->never())
            ->method('load');
        $this->regionPageletLoader->expects($this->once())
            ->method('loadCities')
            ->with('query-state-id', $request, $context)
            ->willReturn(new CountryRegionDataPagelet());

        $response = $this->controller->getCityData($request, $context);

        static::assertSame(
            ['cities' => []],
            \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testGetCityDataThrowsExceptionWithoutCountryStateId(): void
    {
        $this->pageletLoader->expects($this->never())
            ->method('load');
        $this->regionPageletLoader->expects($this->never())
            ->method('loadCities');

        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryStateId'));

        $this->controller->getCityData(new Request(), Generator::generateSalesChannelContext());
    }

    public function testGetDistrictDataUsesCountryCityIdFromQuery(): void
    {
        $request = new Request(['countryCityId' => 'query-city-id']);
        $context = Generator::generateSalesChannelContext();

        $this->pageletLoader->expects($this->never())
            ->method('load');
        $this->regionPageletLoader->expects($this->once())
            ->method('loadDistricts')
            ->with('query-city-id', $request, $context)
            ->willReturn(new CountryRegionDataPagelet());

        $response = $this->controller->getDistrictData($request, $context);

        static::assertSame(
            ['districts' => []],
            \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testGetDistrictDataThrowsExceptionWithoutCountryCityId(): void
    {
        $this->pageletLoader->expects($this->never())
            ->method('load');
        $this->regionPageletLoader->expects($this->never())
            ->method('loadDistricts');

        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryCityId'));

        $this->controller->getDistrictData(new Request(), Generator::generateSalesChannelContext());
    }
}
