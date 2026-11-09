<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves header/footer content layout assignments with domain-aware priority.
 *
 * Resolution priority (highest to lowest):
 * 1. Domain + SalesChannel (most specific)
 * 2. SalesChannel only
 * 3. Global (null domain, null salesChannel)
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DomainAwareLayoutResolver
{
    /**
     * @param EntityRepository<covariant EntityCollection<covariant AbstractContentLayoutAssignmentEntity>> $repository
     */
    public function resolve(
        SalesChannelContext $context,
        EntityRepository $repository
    ): ?AbstractContentLayoutAssignmentEntity {
        $domainId = $context->getDomainId();
        $salesChannelId = $context->getSalesChannel()->getId();

        $criteria = new Criteria();

        $criteria->addFilter(new OrFilter([
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', $domainId),
                new EqualsFilter('salesChannelId', $salesChannelId),
            ]),
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', null),
                new EqualsFilter('salesChannelId', $salesChannelId),
            ]),
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', null),
                new EqualsFilter('salesChannelId', null),
            ]),
        ]));

        // Sort by specificity: non-null domainId first, then non-null salesChannelId
        $criteria->addSorting(new FieldSorting('domainId', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $criteria->addAssociation('contentLayout');

        return $repository->search($criteria, $context->getContext())->getEntities()->first();
    }
}
