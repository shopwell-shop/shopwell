<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Newsletter\SalesChannel\SalesChannelNewsletterRecipientDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SalesChannelNewsletterRecipientDefinition::class)]
class SalesChannelNewsletterRecipientDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelNewsletterRecipientDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('salesChannel.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
