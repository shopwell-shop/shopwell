<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo\SeoUrl\SalesChannel;

use Shopwell\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class SalesChannelSeoUrlDefinition extends SeoUrlDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
            new EqualsFilter('salesChannelId', null),
        ]));
        if (!$criteria->hasEqualsFilter('isCanonical') && !$criteria->hasEqualsFilter(self::ENTITY_NAME . '.isCanonical')) {
            $criteria->addFilter(new EqualsFilter('isCanonical', true));
        }
        if (!$criteria->hasEqualsFilter('isDeleted') && !$criteria->hasEqualsFilter(self::ENTITY_NAME . '.isDeleted')) {
            $criteria->addFilter(new EqualsFilter('isDeleted', false));
        }
    }
}
