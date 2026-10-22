<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * Turns a flat list of comments into the trees the detail page renders.
 *
 * The whole thread is loaded in one query and grouped in php. There are three alternatives and all of
 * them are worse: recursing into the database once per level is the n+1 that makes a deep thread crawl,
 * the dal has no recursive CTE, and a materialised path or a nested set would have to be reindexed on
 * every single reply - for a thread that is read far more often than it is written, but never on a
 * scale where that trade pays off.
 *
 * The rules the tree enforces, in the order they are applied:
 *
 * 1. only approved comments are rendered. A comment with `status = false` is invisible, and so is
 *    everything below it - a reply whose parent was hidden must not surface as a thread of its own,
 *    because the reader would see an answer to a question that is not there;
 * 2. a comment whose parent id is not part of the set at all is promoted to a root. This is a broken
 *    row, not a hidden one: the parent was deleted by something that did not cascade, or the thread was
 *    truncated by {@see self::MAX_COMMENTS}. Dropping it would lose content silently, so it is shown
 *    where a reader can at least see it;
 * 3. a comment that names itself as its parent is treated like 2 - it would otherwise become its own
 *    ancestor and never reach a root;
 * 4. roots are ordered newest first, replies under one parent oldest first. The two orders differ
 *    because they answer different questions: which thread is new, and in which order the conversation
 *    happened.
 *
 * Pagination counts roots only. A page that cut replies apart from their parent would be unreadable,
 * so a page is ten roots including every reply they carry.
 *
 * @final
 */
#[Package('discovery')]
final class BlogCommentTree
{
    /**
     * The hard ceiling of comments loaded for one entry. Beyond this the rest of the thread is not
     * loaded at all: a thread that long has a moderation problem, and rendering it would cost more
     * memory than the page is worth.
     */
    final public const MAX_COMMENTS = 2000;

    /**
     * @return BlogCommentTreeResult
     */
    public function build(
        BlogCommentCollection $comments,
        int $page = 1,
        int $limit = 10,
        bool $truncated = false,
    ): BlogCommentTreeResult {
        $page = max(1, $page);
        $limit = max(1, $limit);

        /** @var array<string, BlogCommentEntity> $byId */
        $byId = [];

        foreach ($comments as $comment) {
            if (\count($byId) >= self::MAX_COMMENTS) {
                $truncated = true;

                break;
            }

            $byId[$comment->id] = $comment;
        }

        /** @var array<string, list<BlogCommentEntity>> $children */
        $children = [];

        /** @var list<BlogCommentEntity> $roots */
        $roots = [];

        foreach ($byId as $comment) {
            // an unapproved comment never renders, and rule 1 above keeps its replies out with it
            if (!$comment->status) {
                continue;
            }

            $parentId = $comment->parentId;

            if ($parentId === null || $parentId === $comment->id || !isset($byId[$parentId])) {
                $roots[] = $comment;

                continue;
            }

            $children[$parentId][] = $comment;
        }

        usort($roots, self::newestFirst(...));

        $total = \count($roots);
        $offset = ($page - 1) * $limit;

        $nodes = [];

        foreach (\array_slice($roots, $offset, $limit) as $root) {
            $nodes[] = $this->node($root, $children, 1);
        }

        return new BlogCommentTreeResult($nodes, $total, $page, $limit, $truncated);
    }

    /**
     * @param array<string, list<BlogCommentEntity>> $children
     */
    private function node(BlogCommentEntity $comment, array $children, int $depth): BlogCommentNode
    {
        $replies = $children[$comment->id] ?? [];

        usort($replies, self::oldestFirst(...));

        $nodes = [];

        foreach ($replies as $reply) {
            $nodes[] = $this->node($reply, $children, $depth + 1);
        }

        return new BlogCommentNode(
            $comment,
            $nodes,
            $depth,
            min($depth, BlogCommentNode::MAX_INDENT_LEVEL),
        );
    }

    private static function newestFirst(BlogCommentEntity $a, BlogCommentEntity $b): int
    {
        $byTime = self::timestamp($b) <=> self::timestamp($a);

        // the id breaks a tie so two comments written in the same millisecond keep a stable order
        return $byTime !== 0 ? $byTime : strcmp($b->id, $a->id);
    }

    private static function oldestFirst(BlogCommentEntity $a, BlogCommentEntity $b): int
    {
        $byTime = self::timestamp($a) <=> self::timestamp($b);

        return $byTime !== 0 ? $byTime : strcmp($a->id, $b->id);
    }

    private static function timestamp(BlogCommentEntity $comment): int
    {
        return $comment->getCreatedAt()?->getTimestamp() ?? 0;
    }
}
