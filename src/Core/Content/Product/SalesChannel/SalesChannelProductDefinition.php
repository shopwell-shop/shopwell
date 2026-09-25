<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel;

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiCriteriaAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class SalesChannelProductDefinition extends ProductDefinition implements SalesChannelDefinitionInterface
{
    private const PRICE_BASELINE = ['taxId', 'unitId', 'referenceUnit', 'purchaseUnit'];

    public function getEntityClass(): string
    {
        return SalesChannelProductEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelProductCollection::class;
    }

    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$this->hasAvailableFilter($criteria)) {
            $criteria->addFilter(
                new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK)
            );
        }

        // reviews of other customers must not be leaked, therefore the filter is applied on every nesting level
        if ($criteria->hasAssociation('productReviews')) {
            $association = $criteria->getAssociation('productReviews');
            $activeReviewsFilter = new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)]);
            if ($customer = $context->getCustomer()) {
                $activeReviewsFilter->addQuery(new EqualsFilter('customerId', $customer->getId()));
            }

            $association->addFilter($activeReviewsFilter);
        }

        if ($criteria->getNestingLevel() !== Criteria::ROOT_NESTING_LEVEL) {
            return;
        }

        if ($criteria->getFields() === []) {
            $criteria
                ->addAssociation('prices')
                ->addAssociation('unit')
                ->addAssociation('deliveryTime')
                ->addAssociation('cover.media')
                ->addAssociation('tax')
            ;
        }
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new JsonField('calculated_price', 'calculatedPrice'))->addFlags(new ApiAware(), new Runtime(\array_merge(self::PRICE_BASELINE, ['price', 'prices'])))
        );
        $fields->add(
            (new ListField('calculated_prices', 'calculatedPrices'))->addFlags(new ApiAware(), new Runtime(\array_merge(self::PRICE_BASELINE, ['prices'])))
        );
        $fields->add(
            (new IntField('calculated_max_purchase', 'calculatedMaxPurchase'))->addFlags(new ApiAware(), new Runtime(['maxPurchase']))
        );
        $fields->add(
            (new JsonField('calculated_cheapest_price', 'calculatedCheapestPrice'))->addFlags(new ApiAware(), new Runtime(\array_merge(self::PRICE_BASELINE, ['cheapestPrice'])))
        );
        $fields->add(
            (new BoolField('is_new', 'isNew'))->addFlags(new ApiAware(), new Runtime(['releaseDate']))
        );
        $fields->add(
            (new OneToOneAssociationField('seoCategory', 'seoCategory', 'id', CategoryDefinition::class))->addFlags(new ApiAware(), new Runtime())->setDescription('Main category used for SEO URL generation in the current sales channel')
        );
        $fields->add(
            (new CheapestPriceField('cheapest_price', 'cheapestPrice'))->addFlags(new WriteProtected(), new Inherited(), new ApiCriteriaAware())
        );
        $fields->add(
            (new ObjectField('cheapest_price_container', 'cheapestPriceContainer'))->addFlags(new Runtime())
        );
        $fields->add(
            (new ObjectField('sortedProperties', 'sortedProperties'))->addFlags(new Runtime(), new ApiAware())
        );

        $fields->add(
            (new ObjectField('measurements', 'measurements'))->addFlags(new Runtime(), new ApiAware(), new Since('6.7.1.0'))
        );

        return $fields;
    }

    private function hasAvailableFilter(Criteria $criteria): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof ProductAvailableFilter) {
                return true;
            }
        }

        return false;
    }
}
