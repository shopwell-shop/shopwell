<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * One comment of a thread together with the replies it carries.
 *
 * The node is what the template walks: `comment` is the row itself, `children` are the replies as
 * nodes of their own, which is what makes the recursion in twig a single include of itself instead of
 * a fixed number of nesting levels.
 *
 * `depth` is the real distance from the root (1 for a root comment) while `indentLevel` is capped at
 * {@see self::MAX_INDENT_LEVEL}. The two differ on purpose: a thread deeper than four levels loses its
 * visual indentation - on a phone the content column would shrink to a vertical line - but the depth
 * itself keeps counting, so the template can still render a level badge and the write route still
 * knows the true depth.
 *
 * A node with more than {@see self::COLLAPSE_AFTER} replies splits them: the first three are rendered
 * open, the rest travel in `collapsedChildren` behind a "show the remaining N replies" toggle. A
 * single popular comment then cannot push every other thread off the page.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class BlogCommentNode
{
    /**
     * How many replies of one parent are rendered without a toggle.
     */
    final public const COLLAPSE_AFTER = 3;

    /**
     * The deepest level that still gets an indentation. Deeper replies keep their depth but share the
     * indent of level four and are separated by a border instead of by whitespace.
     */
    final public const MAX_INDENT_LEVEL = 4;

    /**
     * @param list<BlogCommentNode> $children
     */
    public function __construct(
        private readonly BlogCommentEntity $comment,
        private readonly array $children = [],
        private readonly int $depth = 1,
        private readonly int $indentLevel = 1,
    ) {
    }

    public function getComment(): BlogCommentEntity
    {
        return $this->comment;
    }

    /**
     * @return list<BlogCommentNode>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getIndentLevel(): int
    {
        return $this->indentLevel;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * The replies that are rendered without asking the reader first.
     *
     * @return list<BlogCommentNode>
     */
    public function getVisibleChildren(): array
    {
        return \array_slice($this->children, 0, self::COLLAPSE_AFTER);
    }

    /**
     * The replies behind the "show the remaining N replies" toggle.
     *
     * @return list<BlogCommentNode>
     */
    public function getCollapsedChildren(): array
    {
        return \array_slice($this->children, self::COLLAPSE_AFTER);
    }

    public function getCollapsedCount(): int
    {
        return max(0, \count($this->children) - self::COLLAPSE_AFTER);
    }

    public function hasCollapsedChildren(): bool
    {
        return $this->getCollapsedCount() > 0;
    }
}
