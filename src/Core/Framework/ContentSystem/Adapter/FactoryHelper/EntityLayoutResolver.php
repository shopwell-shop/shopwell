<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopwell\Core\Framework\ContentSystem\PlaceholderValues;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves content layout assignments and processes placeholders for entity-based rendering.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class EntityLayoutResolver
{
    /**
     * Returns only the content layout ID without loading the full assignment or contentLayout association.
     *
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    public function findLayoutId(
        string $entityIdField,
        string $entityId,
        SalesChannelContext $context,
        EntityRepository $repository
    ): ?string {
        $criteria = $this->buildAssignmentCriteria($entityIdField, $entityId, $context);

        $entity = $repository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$entity instanceof AbstractContentLayoutAssignmentEntity) {
            return null;
        }

        return $entity->getContentLayoutId();
    }

    public function resolvePlaceholders(
        string $entityIdField,
        string $entityId,
        Request $request
    ): PlaceholderValues {
        $scalarParameters = array_filter($request->query->all(), '\is_scalar');
        unset($scalarParameters[$entityIdField]);

        return PlaceholderValues::from(array_merge(
            [$entityIdField => $entityId],
            $scalarParameters
        ));
    }

    private function buildAssignmentCriteria(string $entityIdField, string $entityId, SalesChannelContext $context): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($entityIdField, $entityId));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
            new EqualsFilter('salesChannelId', null),
        ]));
        $criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        return $criteria;
    }
}
