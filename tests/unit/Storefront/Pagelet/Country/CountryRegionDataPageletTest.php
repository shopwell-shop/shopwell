<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Pagelet\Country;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityCollection;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityEntity;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictCollection;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictEntity;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPagelet;
use Shopwell\Storefront\Pagelet\Pagelet;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CountryRegionDataPagelet::class)]
class CountryRegionDataPageletTest extends TestCase
{
    public function testIsEmptyByDefault(): void
    {
        $pagelet = new CountryRegionDataPagelet();

        static::assertCount(0, $pagelet->getCities());
        static::assertCount(0, $pagelet->getDistricts());
    }

    public function testSetCities(): void
    {
        $city = (new CountryCityEntity())->assign(['id' => Uuid::randomHex(), 'name' => '市辖区']);
        $cities = new CountryCityCollection([$city]);

        $pagelet = new CountryRegionDataPagelet();
        $pagelet->setCities($cities);

        static::assertSame($cities, $pagelet->getCities());
        static::assertSame($city, $pagelet->getCities()->first());
        // the cities of a province must not leak into the district level
        static::assertCount(0, $pagelet->getDistricts());
    }

    public function testSetDistricts(): void
    {
        $district = (new CountryDistrictEntity())->assign(['id' => Uuid::randomHex(), 'name' => '东城区']);
        $districts = new CountryDistrictCollection([$district]);

        $pagelet = new CountryRegionDataPagelet();
        $pagelet->setDistricts($districts);

        static::assertSame($districts, $pagelet->getDistricts());
        static::assertSame($district, $pagelet->getDistricts()->first());
        static::assertCount(0, $pagelet->getCities());
    }

    public function testExtendsPagelet(): void
    {
        static::assertInstanceOf(Pagelet::class, new CountryRegionDataPagelet());
    }
}
