<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Country\Aggregate\CountryState\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryState\SalesChannel\SalesChannelCountryStateDefinition;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(SalesChannelCountryStateDefinition::class)]
class SalesChannelCountryStateDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelCountryStateDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('country_state.country.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
