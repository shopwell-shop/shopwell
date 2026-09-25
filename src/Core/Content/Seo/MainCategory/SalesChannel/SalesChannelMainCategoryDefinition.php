<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo\MainCategory\SalesChannel;

use Shopwell\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class SalesChannelMainCategoryDefinition extends MainCategoryDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
    }
}
