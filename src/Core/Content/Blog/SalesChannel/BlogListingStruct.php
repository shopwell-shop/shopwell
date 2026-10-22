<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\SalesChannel;

use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * What the `blog-listing` cms element renders: a window of entries, their covers and a density.
 *
 * The struct exists instead of letting the element resolver poke at a page, because the element can be
 * placed anywhere - a landing page, a category, the body of an article - and in none of those places
 * is there a BlogPage to borrow from.
 *
 * `layout` is the only setting of the element and it lives here and not in the shop configuration.
 * Two listings on the same page may want different densities, and a shop wide switch would force one
 * on both.
 *
 * Filled by {@see \Shopwell\Core\Content\Blog\Service\BlogListingReader}, which is also what the
 * storefront listing page reads, so the element and the page can not render two different orders.
 */
#[Package('discovery')]
class BlogListingStruct extends Struct
{
    final public const LAYOUT_CLASSIC = 'classic';

    final public const LAYOUT_GRID = 'grid';

    final public const LAYOUT_LIST = 'list';

    /**
     * Every layout the core renders. An unknown value falls back to classic, so a theme that removed a
     * layout can not leave the element without a container class.
     */
    final public const LAYOUTS = [self::LAYOUT_CLASSIC, self::LAYOUT_GRID, self::LAYOUT_LIST];

    protected ?BlogCollection $blogs = null;

    protected ?MediaCollection $coverMedia = null;

    /**
     * Reading time in minutes per entry id, computed by the loader so the element and the listing page
     * can not disagree about a number a reader compares.
     *
     * @var array<string, int>
     */
    protected array $readingTimes = [];

    protected string $layout = self::LAYOUT_CLASSIC;

    protected int $page = 1;

    protected int $totalPages = 0;

    protected int $total = 0;

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

    public function getLayout(): string
    {
        return $this->layout;
    }

    /**
     * An unknown layout is normalised here and not in the template: a stray class name in the markup
     * is a styling bug that only appears on the one page the editor configured.
     */
    public function setLayout(string $layout): void
    {
        $this->layout = \in_array($layout, self::LAYOUTS, true) ? $layout : self::LAYOUT_CLASSIC;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
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

    public function getApiAlias(): string
    {
        return 'cms_blog_listing';
    }
}
