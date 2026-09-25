<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Product;

use Shopwell\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopwell\Core\Content\Property\PropertyGroupCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

#[Package('inventory')]
class ProductPage extends Page
{
    protected SalesChannelProductEntity $product;

    protected CmsPageEntity $cmsPage;

    protected ?string $navigationId = null;

    protected PropertyGroupCollection $configuratorSettings;

    protected PropertyGroupOptionCollection $selectedOptions;

    /**
     * A small sample of approved reviews used exclusively for JSON-LD structured data output.
     * This contains at most ProductPageLoader::MAX_REVIEWS_IN_JSON_LD items — it must NOT be
     * used to display reviews in templates.
     */
    protected ?ProductReviewResult $structuredDataReviews = null;

    protected ?BreadcrumbCollection $breadcrumb = null;

    public function getProduct(): SalesChannelProductEntity
    {
        return $this->product;
    }

    public function setProduct(SalesChannelProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }

    public function getConfiguratorSettings(): PropertyGroupCollection
    {
        return $this->configuratorSettings;
    }

    public function setConfiguratorSettings(PropertyGroupCollection $configuratorSettings): void
    {
        $this->configuratorSettings = $configuratorSettings;
    }

    public function getSelectedOptions(): PropertyGroupOptionCollection
    {
        return $this->selectedOptions;
    }

    public function setSelectedOptions(PropertyGroupOptionCollection $selectedOptions): void
    {
        $this->selectedOptions = $selectedOptions;
    }

    public function getStructuredDataReviews(): ?ProductReviewResult
    {
        return $this->structuredDataReviews;
    }

    public function setStructuredDataReviews(ProductReviewResult $structuredDataReviews): void
    {
        $this->structuredDataReviews = $structuredDataReviews;
    }

    public function getBreadcrumb(): ?BreadcrumbCollection
    {
        return $this->breadcrumb;
    }

    public function setBreadcrumb(?BreadcrumbCollection $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
    }

    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
