<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
class CompositeProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testComposition(): void
    {
        $request = new Request();
        $criteria = new Criteria();
        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $request->query->set('no-aggregations', true);
        static::getContainer()->get(CompositeListingProcessor::class)->prepare($request, $criteria, $context);
        static::assertEmpty($criteria->getAggregations());

        $request->query->set('only-aggregations', true);
        static::getContainer()->get(CompositeListingProcessor::class)->prepare($request, $criteria, $context);
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getAssociations());
        static::assertSame(0, $criteria->getLimit());
        static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
    }
}
