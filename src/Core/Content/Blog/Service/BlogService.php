<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * The editorial lifecycle of an entry: taking it live and scheduling it.
 *
 * Publishing is more than setting a date. An entry that was never dated has to be dated at the moment
 * it goes live, otherwise the "newest first" sorting of the listing would put every undated entry
 * behind the dated ones forever. An entry that already carries a date keeps it - re-publishing a
 * scheduled entry must not rewrite its original go-live date, because the date is what a reader sees
 * next to the article.
 *
 * Scheduling is the same write with an explicit moment, and it is the only way to take an entry down
 * again: the domain has no `active` flag, so "not for the public yet" is expressed as a date in the
 * future instead of as a second state next to the date. That keeps exactly one field authoritative
 * and lets {@see BlogVisibilityGuard} stay a single comparison.
 *
 * The service is the only writer of `publishedAt`, which is what keeps the rule above true for the
 * administration, an import and a scheduled task alike.
 *
 * @final
 */
#[Package('discovery')]
class BlogService
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly EntityRepository $blogRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Takes the entry live right now. An entry that has no go-live date yet gets the current moment,
     * so it sorts like the newest article instead of like an undated one.
     *
     * @throws BlogException when the entry does not exist
     */
    public function publish(string $blogId, Context $context): void
    {
        $blog = $this->load($blogId, $context);

        $payload = ['id' => $blogId];

        if ($blog->publishedAt === null) {
            $payload['publishedAt'] = $this->clock->now();
        }

        // nothing to write when the entry already carries its date: an update without a changed field
        // still bumps `updated_at` and still emits a written event for every listener to process
        if (\count($payload) === 1) {
            return;
        }

        $this->blogRepository->update([$payload], $context);
    }

    /**
     * Puts the entry on a date. A moment in the future is a scheduled entry - the guard hides it until
     * then - and a moment in the past publishes it retroactively, which is what an import of old
     * articles needs.
     *
     * @throws BlogException when the entry does not exist
     */
    public function schedule(string $blogId, \DateTimeInterface $publishedAt, Context $context): void
    {
        $this->load($blogId, $context);

        $this->blogRepository->update([
            [
                'id' => $blogId,
                'publishedAt' => $publishedAt,
            ],
        ], $context);
    }

    /**
     * @throws BlogException when the entry does not exist
     */
    private function load(string $blogId, Context $context): BlogEntity
    {
        $criteria = new Criteria([$blogId]);
        $criteria->setLimit(1);

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        if (!$blog instanceof BlogEntity) {
            throw BlogException::notFound($blogId);
        }

        return $blog;
    }
}
