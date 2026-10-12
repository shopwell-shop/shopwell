<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Country\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictCollection;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictEntity;
use Shopwell\Core\System\Country\SalesChannel\CountryDistrictRouteResponse;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryDistrictRouteResponse::class)]
class CountryDistrictRouteResponseTest extends TestCase
{
    public function testGetDistricts(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $district = (new CountryDistrictEntity())->assign(['id' => Uuid::randomHex(), 'name' => '东城区']);
        $districts = new CountryDistrictCollection([$district]);

        $response = new CountryDistrictRouteResponse(new EntitySearchResult(
            CountryDistrictDefinition::ENTITY_NAME,
            1,
            $districts,
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        ));

        static::assertSame($districts, $response->getDistricts());
        static::assertSame($district, $response->getDistricts()->first());
        static::assertSame($response->getDistricts(), $response->getObject()->getEntities());
    }

    public function testGetDistrictsReturnsEmptyCollectionWhenNothingWasFound(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $response = new CountryDistrictRouteResponse(new EntitySearchResult(
            CountryDistrictDefinition::ENTITY_NAME,
            0,
            new CountryDistrictCollection(),
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        ));

        static::assertCount(0, $response->getDistricts());
    }
}
