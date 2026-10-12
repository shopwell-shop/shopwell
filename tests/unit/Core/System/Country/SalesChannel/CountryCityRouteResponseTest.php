<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Country\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityCollection;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityEntity;
use Shopwell\Core\System\Country\SalesChannel\CountryCityRouteResponse;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryCityRouteResponse::class)]
class CountryCityRouteResponseTest extends TestCase
{
    public function testGetCities(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $city = (new CountryCityEntity())->assign(['id' => Uuid::randomHex(), 'name' => '市辖区']);
        $cities = new CountryCityCollection([$city]);

        $response = new CountryCityRouteResponse(new EntitySearchResult(
            CountryCityDefinition::ENTITY_NAME,
            1,
            $cities,
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        ));

        static::assertSame($cities, $response->getCities());
        static::assertSame($city, $response->getCities()->first());
        static::assertSame($response->getCities(), $response->getObject()->getEntities());
    }

    public function testGetCitiesReturnsEmptyCollectionWhenNothingWasFound(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $response = new CountryCityRouteResponse(new EntitySearchResult(
            CountryCityDefinition::ENTITY_NAME,
            0,
            new CountryCityCollection(),
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        ));

        static::assertCount(0, $response->getCities());
    }
}
