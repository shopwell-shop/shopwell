<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Store;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\ExtensionRemovalValidatorInterface;
use Shopwell\Core\Framework\Store\StoreException;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Storefront\Theme\ThemeCollection;

/**
 * Blocks removing an extension whose theme, or a child of it, is still assigned to a sales channel.
 *
 * @internal
 */
#[Package('discovery')]
class ThemeExtensionRemovalValidator implements ExtensionRemovalValidatorInterface
{
    /**
     * @param EntityRepository<ThemeCollection> $themeRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $themeRepository,
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    public function validateCanBeRemoved(string $technicalName, string $extensionId, Context $context): void
    {
        $themeId = $this->themeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName)),
            $context
        )->firstId();

        if ($themeId === null) {
            // extension is not a theme
            return;
        }

        $criteria = new Criteria();
        $criteria->addAggregation(
            new FilterAggregation(
                'assigned_theme_filter',
                new TermsAggregation('assigned_theme', 'themes.id'),
                [new EqualsFilter('themes.id', $themeId)]
            )
        );
        $criteria->addAggregation(
            new FilterAggregation(
                'assigned_children_filter',
                new TermsAggregation('assigned_children', 'themes.parentThemeId'),
                [new EqualsFilter('themes.parentThemeId', $themeId)]
            )
        );

        $aggregates = $this->salesChannelRepository->aggregate($criteria, $context);

        /** @var TermsResult $directlyAssigned */
        $directlyAssigned = $aggregates->get('assigned_theme');

        /** @var TermsResult $assignedChildren */
        $assignedChildren = $aggregates->get('assigned_children');

        if ($directlyAssigned->getKeys() !== [] || $assignedChildren->getKeys() !== []) {
            throw StoreException::extensionThemeStillInUse($extensionId);
        }
    }
}
