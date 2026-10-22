<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;

/**
 * The one place that decides whether a visitor may read an entry.
 *
 * There is exactly one dimension: the date. An entry without a `publishedAt` is live the moment it is
 * saved, an entry dated in the future is a row nobody may read yet, and an entry dated in the past is
 * readable. That is the whole rule.
 *
 * There used to be a second switch next to it and it was removed: a flag that says "this content
 * exists but is switched off" creates states no template can render sensibly - enabled but undated,
 * dated but disabled - and every call site would have to decide what those mean. A merchant who
 * wants to take an article down either deletes it or moves its date forward; both are one field and
 * both are already understood by the rule below.
 *
 * Keeping the rule here instead of in the storefront route means a sitemap, a listing, an rss feed and
 * a search result cannot disagree about what is published - the mistake that makes a scheduled entry
 * leak through one of the four. The rule is offered in both shapes a caller needs:
 *
 * - {@see self::isVisible()} answers for one already loaded entry, the detail page uses it;
 * - {@see self::getVisibilityFilter()} answers for the whole table, the listing and the sitemap use it
 *   so that limit and offset stay meaningful and a page is never half empty because invisible rows
 *   were loaded and dropped afterwards;
 * - {@see self::filterVisible()} narrows a collection that was loaded for another reason.
 *
 * The clock is injected rather than read from the system so the boundary can be tested: an entry that
 * goes live exactly now is visible, and a test can put "now" where it needs it.
 *
 * @final
 */
#[Package('discovery')]
class BlogVisibilityGuard
{
    /**
     * @internal
     */
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * Whether the entry is readable in the given context.
     *
     * The context is part of the signature although the rule itself is shop wide today: every call
     * site has one at hand, and visibility that depends on the sales channel (an entry announced in
     * one channel only) would otherwise change the signature of every caller.
     */
    public function isVisible(BlogEntity $blog, Context $context): bool
    {
        $publishedAt = $blog->publishedAt;

        if ($publishedAt === null) {
            return true;
        }

        return $publishedAt <= $this->clock->now();
    }

    /**
     * The same rule as a dal filter, for everything that queries entries instead of inspecting them.
     *
     * It is deliberately not simplified to `publishedAt <= now`: a null date is a published entry, so
     * the null branch has to be spelled out. A plain range filter would silently drop every entry an
     * editor saved without picking a date, which is the common case.
     */
    public function getVisibilityFilter(): Filter
    {
        return new OrFilter([
            new EqualsFilter('publishedAt', null),
            new RangeFilter('publishedAt', [RangeFilter::LTE => $this->clock->now()->format('Y-m-d H:i:s.v')]),
        ]);
    }

    /**
     * Narrows an already loaded collection to the entries a visitor may see, keeping the order.
     */
    public function filterVisible(BlogCollection $blogs, Context $context): BlogCollection
    {
        $visible = new BlogCollection();

        foreach ($blogs as $blog) {
            if ($this->isVisible($blog, $context)) {
                $visible->add($blog);
            }
        }

        return $visible;
    }
}
