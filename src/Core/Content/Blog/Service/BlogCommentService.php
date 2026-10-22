<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The only way a comment enters the database.
 *
 * Four things are checked before the row exists, and every one of them guards against a request that
 * a browser would never send:
 *
 * - the parent must exist. The id comes from the request body and may be anything;
 * - the parent must belong to the same entry. Without this a reply could be hung below a comment of
 *   another article and would render there, in a thread it was never written for;
 * - the parent must be approved. Answering a comment nobody can read produces a thread that starts in
 *   the middle;
 * - the thread must stay within {@see self::MAX_DEPTH} levels. The reply is rejected rather than
 *   attached to the deepest allowed comment: silently moving it would tell the author it succeeded
 *   while showing it somewhere else.
 *
 * The depth is walked up the `parent_id` chain on every write and never stored. A stored depth is a
 * second copy of a fact that the chain already holds, and it goes stale the moment a comment is moved
 * or a subtree is deleted - the walk is at most five tiny lookups and only on write.
 *
 * `status` is never taken from the request. It comes from {@see BlogSettings::autoPublishComments()},
 * so a shop that moderates cannot be talked into publishing by a crafted payload.
 *
 * @final
 */
#[Package('discovery')]
final class BlogCommentService
{
    /**
     * The deepest level a comment may live on. A root comment is level 1, a reply to it level 2, and a
     * reply to a level five comment would be level six and is refused.
     */
    final public const MAX_DEPTH = 5;

    /**
     * A defensive bound for the walk up the chain. A cycle can only exist if rows were written around
     * this service, and the walk has to terminate even then.
     */
    private const CHAIN_GUARD = 64;

    /**
     * @internal
     *
     * @param EntityRepository<BlogCommentCollection> $commentRepository
     */
    public function __construct(
        private readonly EntityRepository $commentRepository,
        private readonly BlogCommentFloodGuard $floodGuard,
        private readonly BlogSettings $settings,
    ) {
    }

    /**
     * Stores the comment and answers with its id.
     *
     * @throws BlogException when the parent is unusable, the thread is too deep or the address posts twice
     */
    public function create(BlogCommentDraft $draft, Context $context): string
    {
        $this->assertParentIsUsable($draft, $context);

        $this->floodGuard->assertMayPost($draft->blogId, (string) $draft->externalEmail, $context);

        $id = Uuid::randomHex();

        $this->commentRepository->create([
            [
                'id' => $id,
                'blogId' => $draft->blogId,
                'parentId' => $draft->parentId,
                'customerId' => $draft->customerId,
                'salesChannelId' => $draft->salesChannelId,
                'languageId' => $draft->languageId,
                'externalUser' => $draft->externalUser,
                'externalEmail' => $draft->externalEmail,
                'title' => $draft->title,
                'content' => $draft->content,
                'status' => $this->settings->autoPublishComments($draft->salesChannelId),
            ],
        ], $context);

        return $id;
    }

    /**
     * @throws BlogException
     */
    private function assertParentIsUsable(BlogCommentDraft $draft, Context $context): void
    {
        if ($draft->parentId === null) {
            return;
        }

        $parent = $this->load($draft->parentId, $context);

        if (!$parent instanceof BlogCommentEntity) {
            throw BlogException::commentParentNotFound($draft->parentId);
        }

        if ($parent->blogId !== $draft->blogId) {
            throw BlogException::commentParentForeignBlog($draft->parentId, $draft->blogId);
        }

        if (!$parent->status) {
            throw BlogException::commentParentNotApproved($draft->parentId);
        }

        if ($this->depthOf($parent, $context) + 1 > self::MAX_DEPTH) {
            throw BlogException::commentDepthExceeded(self::MAX_DEPTH);
        }
    }

    /**
     * The level the given comment lives on: 1 for a root, 2 for a reply to a root, and so on.
     *
     * @throws BlogException when the chain does not end, which only a hand written cycle can cause
     */
    private function depthOf(BlogCommentEntity $comment, Context $context): int
    {
        $depth = 1;
        $seen = [$comment->id => true];
        $parentId = $comment->parentId;

        while ($parentId !== null) {
            if (isset($seen[$parentId]) || $depth >= self::CHAIN_GUARD) {
                throw BlogException::commentDepthExceeded(self::MAX_DEPTH);
            }

            $seen[$parentId] = true;

            $parent = $this->load($parentId, $context);

            // the chain ends in a row that is gone; the comment above it is the highest reachable one
            if (!$parent instanceof BlogCommentEntity) {
                break;
            }

            $depth++;
            $parentId = $parent->parentId;
        }

        return $depth;
    }

    private function load(string $commentId, Context $context): ?BlogCommentEntity
    {
        $criteria = new Criteria([$commentId]);
        $criteria->setLimit(1);

        $comment = $this->commentRepository->search($criteria, $context)->getEntities()->first();

        return $comment instanceof BlogCommentEntity ? $comment : null;
    }
}
