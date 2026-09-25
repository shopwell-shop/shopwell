<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Language\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\SalesChannel\SalesChannelLanguageDefinition;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(SalesChannelLanguageDefinition::class)]
class SalesChannelLanguageDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelLanguageDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('language.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
