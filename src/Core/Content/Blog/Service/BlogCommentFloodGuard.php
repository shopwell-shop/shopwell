<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;

/**
 * Stops one address from writing the same thread full.
 *
 * The window is counted on `created_at` and not on a counter in the session: a script has no session,
 * and a session based limit is defeated by clearing a cookie. One query per post is cheap next to the
 * insert that follows it.
 *
 * The check is scoped to the entry and to the address. Two colleagues behind one office address may
 * comment on two different articles within a minute, and a reader may comment on two articles in a
 * row - what must not happen is a hundred comments below one article in a minute.
 *
 * Moderation is not a rate limit and this class does not pretend to be one: a comment that passes here
 * is still held back by `status = false` when the shop has not switched auto publishing on.
 *
 * @final
 */
#[Package('discovery')]
final class BlogCommentFloodGuard
{
    /**
     * The cool down between two comments of the same address on the same entry.
     */
    final public const WINDOW_SECONDS = 60;

    /**
     * @internal
     *
     * @param EntityRepository<BlogCommentCollection> $commentRepository
     */
    public function __construct(
        private readonly EntityRepository $commentRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws BlogException when the address already commented on this entry within the window
     */
    public function assertMayPost(string $blogId, string $email, Context $context): void
    {
        if (trim($email) === '') {
            return;
        }

        $since = $this->clock->now()->modify('-' . self::WINDOW_SECONDS . ' seconds');

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('blogId', $blogId),
            new EqualsFilter('externalEmail', $email),
            new RangeFilter('createdAt', [RangeFilter::GTE => $since->format('Y-m-d H:i:s.v')]),
        );
        $criteria->setLimit(1);

        if ($this->commentRepository->searchIds($criteria, $context)->getTotal() > 0) {
            throw BlogException::commentFlood(self::WINDOW_SECONDS);
        }
    }
}
