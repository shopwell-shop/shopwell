<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Blog;

use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryCollection;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

/**
 * The listing of the blog: one page of entries plus everything the page chrome needs.
 *
 * The categories travel with the page and are not fetched by the template: the chip bar has to be
 * filtered down to the categories that actually hold a published entry, and that question can only be
 * answered next to the query it belongs to.
 *
 * The pagination is kept as four plain numbers instead of a struct so the template can hand them
 * straight to the shared `component/pagination.html.twig`, which already knows how to render a window
 * of page links.
 */
#[Package('discovery')]
class BlogPage extends Page
{
    protected ?BlogCollection $blogs = null;

    protected ?BlogCategoryCollection $categories = null;

    /**
     * The cover images of the page's entries, keyed by media id. The entries carry the id only, so the
     * template looks the image up here instead of triggering one query per card.
     */
    protected ?MediaCollection $coverMedia = null;

    /**
     * Reading time in minutes per entry id, computed once by the reader so the card and the detail
     * page can not disagree about it.
     *
     * @var array<string, int>
     */
    protected array $readingTimes = [];

    protected ?string $activeCategoryId = null;

    /**
     * The cms layout of the listing, null when the merchant built none.
     *
     * The layout hangs on the category and not on the shop: "Guides" may be a magazine style grid
     * while "Company news" is a plain list. The listing without a filter has no category and therefore
     * no layout of its own - it falls back to the twig listing, which is the same template a cms
     * element renders.
     */
    protected ?string $cmsPageId = null;

    /**
     * The resolved layout of the filtered category, null when the merchant built none.
     */
    protected ?CmsPageEntity $cmsPage = null;

    protected int $pageNumber = 1;

    protected int $totalPages = 0;

    protected int $total = 0;

    protected int $limit = 12;

    protected bool $showAuthor = true;

    protected bool $showReadingTime = true;

    public function getBlogs(): ?BlogCollection
    {
        return $this->blogs;
    }

    public function setBlogs(BlogCollection $blogs): void
    {
        $this->blogs = $blogs;
    }

    public function getCategories(): ?BlogCategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(BlogCategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    /**
     * The category the visitor filtered by, null for "all". The template marks the matching chip with
     * it; keeping the id and not the entity means a filter by a category that holds no entry anymore
     * still renders the bar correctly.
     */
    public function getActiveCategoryId(): ?string
    {
        return $this->activeCategoryId;
    }

    public function setActiveCategoryId(?string $activeCategoryId): void
    {
        $this->activeCategoryId = $activeCategoryId;
    }

    /**
     * The cms layout of the filtered category, null for the unfiltered listing and for a category
     * without a layout of its own.
     */
    public function getCmsPageId(): ?string
    {
        return $this->cmsPageId;
    }

    public function setCmsPageId(?string $cmsPageId): void
    {
        $this->cmsPageId = $cmsPageId;
    }

    /**
     * The layout the merchant built for the filtered category, null for the twig listing.
     */
    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(?CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getCoverMedia(): ?MediaCollection
    {
        return $this->coverMedia;
    }

    public function setCoverMedia(MediaCollection $coverMedia): void
    {
        $this->coverMedia = $coverMedia;
    }

    /**
     * @return array<string, int>
     */
    public function getReadingTimes(): array
    {
        return $this->readingTimes;
    }

    /**
     * @param array<string, int> $readingTimes
     */
    public function setReadingTimes(array $readingTimes): void
    {
        $this->readingTimes = $readingTimes;
    }

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function setPageNumber(int $pageNumber): void
    {
        $this->pageNumber = $pageNumber;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function setTotalPages(int $totalPages): void
    {
        $this->totalPages = $totalPages;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    public function getShowAuthor(): bool
    {
        return $this->showAuthor;
    }

    public function setShowAuthor(bool $showAuthor): void
    {
        $this->showAuthor = $showAuthor;
    }

    public function getShowReadingTime(): bool
    {
        return $this->showReadingTime;
    }

    public function setShowReadingTime(bool $showReadingTime): void
    {
        $this->showReadingTime = $showReadingTime;
    }

    public function getEntityName(): string
    {
        return 'blog';
    }
}
