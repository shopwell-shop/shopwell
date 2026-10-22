<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Framework\Log\Package;

/**
 * One page of a comment thread, already built into trees.
 *
 * `roots` holds only the top level comments of the current page; every reply travels inside the node
 * of its parent, so a page of ten threads renders ten trees and not ten roots plus a lookup table.
 *
 * `total` counts the top level comments of the whole thread - it is the number the pagination needs,
 * and it deliberately does not count replies. Counting replies would produce a page count that says
 * "page 4 of 9" while only two pages have a root comment on them.
 *
 * `truncated` reports that the thread was longer than {@see BlogCommentTree::MAX_COMMENTS} and only
 * the newest part of it was loaded. A shop that reaches that number has a moderation problem and not
 * a rendering problem, but the page has to say so instead of pretending the thread ends there.
 */
#[Package('discovery')]
final class BlogCommentTreeResult
{
    /**
     * @param list<BlogCommentNode> $roots
     */
    public function __construct(
        private readonly array $roots = [],
        private readonly int $total = 0,
        private readonly int $page = 1,
        private readonly int $limit = 10,
        private readonly bool $truncated = false,
    ) {
    }

    /**
     * @return list<BlogCommentNode>
     */
    public function getRoots(): array
    {
        return $this->roots;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getTotalPages(): int
    {
        if ($this->limit < 1) {
            return 0;
        }

        return (int) ceil($this->total / $this->limit);
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function isEmpty(): bool
    {
        return $this->roots === [];
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->getTotalPages();
    }
}
