<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ManufacturerListingFilterHandler::class)]
class ManufacturerFilterHandlerTest extends TestCase
{
    private ManufacturerListingFilterHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ManufacturerListingFilterHandler();
    }

    public function testCreateWithManufacturerFilterDisabled(): void
    {
        $request = new Request();
        $request->request->set('manufacturer-filter', false);

        $context = static::createStub(SalesChannelContext::class);

        $filter = $this->handler->create($request, $context);

        static::assertNull($filter);
    }

    public function testCreateWithManufacturerFilterEnabled(): void
    {
        $manufacturerIds = ['1', '2', '3'];

        $request = new Request();
        $request->query->set('manufacturer', \implode('|', $manufacturerIds));

        $context = static::createStub(SalesChannelContext::class);

        $filter = $this->handler->create($request, $context);

        static::assertInstanceOf(Filter::class, $filter);
        static::assertSame('manufacturer', $filter->getName());
        static::assertTrue($filter->isFiltered());

        $aggregations = $filter->getAggregations();
        static::assertCount(1, $aggregations);
        static::assertInstanceOf(EntityAggregation::class, $aggregations[0]);
        static::assertSame('manufacturer', $aggregations[0]->getName());
        static::assertSame('product.manufacturerId', $aggregations[0]->getField());
        static::assertSame('product_manufacturer', $aggregations[0]->getEntity());

        $criteriaFilter = $filter->getFilter();
        static::assertInstanceOf(EqualsAnyFilter::class, $criteriaFilter);
        static::assertSame('product.manufacturerId', $criteriaFilter->getField());
        static::assertSame($manufacturerIds, $criteriaFilter->getValue());
        static::assertSame($manufacturerIds, $filter->getValues());
    }

    public function testCreateWithEmptyManufacturerIds(): void
    {
        $request = new Request();
        $request->request->set('manufacturer-filter', true);
        $request->request->set('manufacturer', '');

        $context = static::createStub(SalesChannelContext::class);

        $filter = $this->handler->create($request, $context);

        static::assertInstanceOf(Filter::class, $filter);
        static::assertSame('manufacturer', $filter->getName());
        static::assertFalse($filter->isFiltered());

        $aggregations = $filter->getAggregations();
        static::assertCount(1, $aggregations);
        static::assertInstanceOf(EntityAggregation::class, $aggregations[0]);
        static::assertSame('manufacturer', $aggregations[0]->getName());
        static::assertSame('product.manufacturerId', $aggregations[0]->getField());
        static::assertSame('product_manufacturer', $aggregations[0]->getEntity());

        $criteriaFilter = $filter->getFilter();
        static::assertInstanceOf(EqualsAnyFilter::class, $criteriaFilter);
        static::assertSame('product.manufacturerId', $criteriaFilter->getField());
        static::assertEmpty($criteriaFilter->getValue());

        static::assertEmpty($filter->getValues());
    }
}
