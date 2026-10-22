<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\Service\BlogCommentNode;
use Shopwell\Core\Content\Blog\Service\BlogCommentTree;
use Shopwell\Core\Content\Blog\Service\BlogCommentTreeResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * The thread is grouped in php, so the grouping is where a thread goes wrong: a reply that lands on
 * the wrong parent, a subtree that survives its hidden root, a page boundary that cuts a conversation
 * in half. Every one of those is a case below.
 *
 * The list handed to the builder is deliberately not sorted, because the dal does not promise an order
 * that matches the tree - the caller sorts by `created_at` for the pagination, and the tree still has
 * to come out right when a reply is read before its parent.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogCommentTree::class)]
class BlogCommentTreeTest extends TestCase
{
    private BlogCommentTree $tree;

    protected function setUp(): void
    {
        $this->tree = new BlogCommentTree();
    }

    public function testARepliesParentIsFoundEvenWhenTheParentIsListedAfterIt(): void
    {
        $root = $this->comment('a1', null, '2026-01-01 10:00:00');
        $reply = $this->comment('b1', 'a1', '2026-01-01 10:05:00');
        $answer = $this->comment('c1', 'b1', '2026-01-01 10:06:00');

        // the deepest reply first: a single pass over the list would attach it to nothing
        $result = $this->tree->build(new BlogCommentCollection([$answer, $reply, $root]));

        static::assertSame(1, $result->getTotal());
        static::assertCount(1, $result->getRoots());

        $node = $result->getRoots()[0];
        static::assertSame('a1', $node->getComment()->id);
        static::assertCount(1, $node->getChildren());
        static::assertSame('b1', $node->getChildren()[0]->getComment()->id);
        static::assertSame('c1', $node->getChildren()[0]->getChildren()[0]->getComment()->id);
    }

    public function testACommentWhoseParentIsMissingIsRenderedAsATopLevelCommentInsteadOfBeingDropped(): void
    {
        $orphan = $this->comment('orphan', 'gone', '2026-01-01 10:00:00');
        $root = $this->comment('a1', null, '2026-01-01 09:00:00');

        $result = $this->tree->build(new BlogCommentCollection([$orphan, $root]));

        // promoted, not dropped: the row exists and a reader can still read it. The order is the
        // usual one for roots - the newer comment first, and the orphan is the newer one here.
        static::assertSame(2, $result->getTotal());
        static::assertSame(['orphan', 'a1'], $this->ids($result));
    }

    public function testACommentThatNamesItselfAsItsParentIsRenderedAsATopLevelComment(): void
    {
        $self = $this->comment('loop', 'loop', '2026-01-01 10:00:00');

        $result = $this->tree->build(new BlogCommentCollection([$self]));

        static::assertSame(['loop'], $this->ids($result));
    }

    public function testTheWholeSubtreeOfAHiddenCommentIsNotRendered(): void
    {
        $hidden = $this->comment('hidden', null, '2026-01-01 10:00:00', status: false);
        $visibleReply = $this->comment('reply', 'hidden', '2026-01-01 10:01:00');
        $root = $this->comment('a1', null, '2026-01-01 09:00:00');

        $result = $this->tree->build(new BlogCommentCollection([$root, $hidden, $visibleReply]));

        // the approved reply must not surface as a thread of its own - a reader would see an answer
        // to a question that is not there
        static::assertSame(['a1'], $this->ids($result));
    }

    public function testRootsAreOrderedNewestFirstAndRepliesOldestFirst(): void
    {
        $older = $this->comment('old', null, '2026-01-01 09:00:00');
        $newer = $this->comment('new', null, '2026-01-01 11:00:00');
        $second = $this->comment('r2', 'new', '2026-01-01 11:20:00');
        $first = $this->comment('r1', 'new', '2026-01-01 11:10:00');

        $result = $this->tree->build(new BlogCommentCollection([$older, $second, $newer, $first]));

        static::assertSame(['new', 'old'], $this->ids($result));
        static::assertSame(
            ['r1', 'r2'],
            array_map(static fn (BlogCommentNode $node): string => $node->getComment()->id, $result->getRoots()[0]->getChildren())
        );
    }

    public function testOnlyTheFirstThreeRepliesOfAParentAreRenderedOpenly(): void
    {
        $root = $this->comment('a1', null, '2026-01-01 10:00:00');
        $replies = [];

        foreach (range(1, 5) as $index) {
            $replies[] = $this->comment('r' . $index, 'a1', '2026-01-01 10:0' . $index . ':00');
        }

        $result = $this->tree->build(new BlogCommentCollection([$root, ...$replies]));
        $node = $result->getRoots()[0];

        static::assertTrue($node->hasCollapsedChildren());
        static::assertCount(3, $node->getVisibleChildren());
        static::assertCount(2, $node->getCollapsedChildren());
        static::assertSame(2, $node->getCollapsedCount());
    }

    public function testTheIndentationStopsAtTheFourthLevelWhileTheDepthKeepsCounting(): void
    {
        $comments = [$this->comment('l1', null, '2026-01-01 10:00:00')];

        foreach (range(2, 6) as $level) {
            $comments[] = $this->comment('l' . $level, 'l' . ($level - 1), '2026-01-01 10:0' . $level . ':00');
        }

        $result = $this->tree->build(new BlogCommentCollection($comments));
        $node = $result->getRoots()[0];

        // five steps down from the root: level 6 is the deepest comment of the chain
        for ($step = 0; $step < 5; ++$step) {
            $node = $node->getChildren()[0];
        }

        static::assertSame(6, $node->getDepth());
        static::assertSame(BlogCommentNode::MAX_INDENT_LEVEL, $node->getIndentLevel());
    }

    public function testPaginationCountsTopLevelCommentsOnlyAndKeepsTheirReplies(): void
    {
        $comments = [];

        foreach (range(1, 12) as $index) {
            $comments[] = $this->comment('root-' . $index, null, '2026-01-01 10:' . str_pad((string) $index, 2, '0', \STR_PAD_LEFT) . ':00');
            $comments[] = $this->comment('reply-' . $index, 'root-' . $index, '2026-01-01 11:00:00');
        }

        $first = $this->tree->build(new BlogCommentCollection($comments), 1, 10);
        $second = $this->tree->build(new BlogCommentCollection($comments), 2, 10);

        static::assertSame(12, $first->getTotal());
        static::assertSame(2, $first->getTotalPages());
        static::assertCount(10, $first->getRoots());
        static::assertCount(2, $second->getRoots());

        // the newer roots come first, and every root of the page carries its reply
        static::assertSame('root-12', $first->getRoots()[0]->getComment()->id);
        static::assertCount(1, $first->getRoots()[0]->getChildren());
        static::assertTrue($first->hasNextPage());
        static::assertFalse($second->hasNextPage());
    }

    public function testAnEmptyThreadRendersNothingButIsNotAnError(): void
    {
        $result = $this->tree->build(new BlogCommentCollection());

        static::assertTrue($result->isEmpty());
        static::assertSame(0, $result->getTotal());
        static::assertSame(0, $result->getTotalPages());
        static::assertFalse($result->isTruncated());
    }

    public function testTheCeilingMarksTheResultAsTruncated(): void
    {
        $comments = [];

        foreach (range(1, 5) as $index) {
            $comments[] = $this->comment('root-' . $index, null, '2026-01-01 10:0' . $index . ':00');
        }

        $result = $this->tree->build(new BlogCommentCollection($comments), 1, 10, true);

        static::assertTrue($result->isTruncated());
    }

    /**
     * @return list<string>
     */
    private function ids(BlogCommentTreeResult $result): array
    {
        return array_map(
            static fn (BlogCommentNode $node): string => $node->getComment()->id,
            $result->getRoots()
        );
    }

    private function comment(string $id, ?string $parentId, string $createdAt, bool $status = true): BlogCommentEntity
    {
        $comment = new BlogCommentEntity();
        $comment->id = $id;
        // the collection indexes by unique identifier, and the dal sets it on hydrate normally
        $comment->setUniqueIdentifier($id);
        $comment->blogId = 'blog-id';
        $comment->parentId = $parentId;
        $comment->salesChannelId = 'sales-channel-id';
        $comment->languageId = 'language-id';
        $comment->content = 'A comment';
        $comment->status = $status;
        $comment->setCreatedAt(new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC')));

        return $comment;
    }
}
