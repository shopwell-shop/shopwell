<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogVideo\BlogVideoEntity;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads one article and everything that hangs on it: author, cover, video, table of contents, reading
 * time, related entries and the comment thread.
 *
 * The class is the query layer for the detail page and stops at data. It produces a
 * {@see BlogDetailStruct} and no page object, which is what keeps the cms layout, the meta information
 * and the page loaded event in the storefront where they belong.
 *
 * Everything a template would otherwise compute is prepared here, because a twig template that parses
 * html or groups a thread is both slow and untestable.
 *
 * @final
 */
#[Package('discovery')]
class BlogDetailReader
{
    /**
     * Associations the detail page needs. `coverMedia` is not among them: the entity carries the id
     * only, so the image is loaded on demand by {@see self::coverMedia()}.
     */
    private const ASSOCIATIONS = [
        'author',
        'categories.blogCategory',
        'videos',
    ];

    /**
     * @internal
     *
     * @param EntityRepository<BlogCollection> $blogRepository
     * @param EntityRepository<BlogCommentCollection> $commentRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $blogRepository,
        private readonly EntityRepository $commentRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly BlogVisibilityGuard $visibilityGuard,
        private readonly BlogSettings $settings,
        private readonly BlogCommentTree $commentTree,
        private readonly BlogReadingTimeEstimator $readingTimeEstimator,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    /**
     * @throws BlogException when the entry does not exist or is not published yet
     */
    public function read(string $blogId, Request $request, SalesChannelContext $salesChannelContext): BlogDetailStruct
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $context = $salesChannelContext->getContext();

        $criteria = new Criteria([$blogId]);

        foreach (self::ASSOCIATIONS as $association) {
            $criteria->addAssociation($association);
        }

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        // a scheduled entry is answered with the same 404 as a missing one: telling a visitor that the
        // page exists but is not ready yet leaks the editorial calendar
        if (!$blog instanceof BlogEntity || !$this->visibilityGuard->isVisible($blog, $context)) {
            throw BlogException::notFound($blogId);
        }

        $content = $blog->content ?? '';
        $tocEnabled = $this->settings->isTocEnabled($salesChannelId);

        $struct = new BlogDetailStruct();
        $struct->setBlog($blog);
        $struct->setAuthor($blog->author instanceof BlogAuthorEntity ? $blog->author : null);
        $struct->setCoverMedia($this->coverMedia($blog, $context));
        $struct->setVideo($this->videoOf($blog));
        $struct->setToc($tocEnabled ? $this->tableOfContents($content) : []);
        $struct->setContent($this->withAnchors($content));
        $struct->setTocEnabled($tocEnabled);
        $struct->setReadingTimeMinutes($this->readingTimeEstimator->estimate($content));
        $struct->setShowAuthor($this->settings->showAuthor($salesChannelId));
        $struct->setShowReadingTime($this->settings->showReadingTime($salesChannelId));
        $struct->setRelatedBlogs($this->relatedBlogs($blog, $salesChannelId, $context));

        $commentsEnabled = $this->settings->areCommentsEnabled($salesChannelId);
        $commentsPerPage = $this->settings->getCommentsPerPage($salesChannelId);

        $struct->setCommentsEnabled($commentsEnabled);
        $struct->setCommentsAutoPublish($this->settings->autoPublishComments($salesChannelId));
        $struct->setCommentsPerPage($commentsPerPage);
        $struct->setComments(
            $commentsEnabled
                ? $this->comments($blog, $request, $commentsPerPage, $salesChannelContext)
                : $this->commentTree->build(new BlogCommentCollection(), 1, $commentsPerPage)
        );

        return $struct;
    }

    /**
     * The comment thread of one entry, one query, grouped in memory.
     *
     * The whole thread is fetched - approved and pending alike - and not only the approved rows. The
     * difference matters: a reply below a comment that a moderator hid has to disappear with its
     * parent, and from the approved rows alone that reply is indistinguishable from a reply whose
     * parent was deleted. Loading both lets {@see BlogCommentTree} tell the two apart.
     *
     * The order is oldest first so that the ceiling of {@see BlogCommentTree::MAX_COMMENTS} cuts the
     * newest end of an extreme thread and keeps the beginning, which is what the first page shows.
     */
    private function comments(
        BlogEntity $blog,
        Request $request,
        int $perPage,
        SalesChannelContext $salesChannelContext,
    ): BlogCommentTreeResult {
        // the sales channel comes from the context, not from the generic Context: only the sales
        // channel context knows which channel the visitor is reading, and a thread must not mix
        // comments of two channels that happen to share an entry
        $context = $salesChannelContext->getContext();
        $page = max(1, (int) $request->query->get('comments', '1'));

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('blogId', $blog->id),
            new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannelId()),
        );
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit(BlogCommentTree::MAX_COMMENTS);

        $result = $this->commentRepository->search($criteria, $context);

        /** @var BlogCommentCollection $comments */
        $comments = $result->getEntities();

        return $this->commentTree->build(
            $comments,
            $page,
            $perPage,
            $result->getTotal() > BlogCommentTree::MAX_COMMENTS,
        );
    }

    /**
     * The entries a reader is most likely to open next: same category first, topped up with the
     * newest ones so a young blog does not render a half empty block.
     */
    private function relatedBlogs(BlogEntity $blog, string $salesChannelId, Context $context): BlogCollection
    {
        $limit = $this->settings->getRelatedPostsCount($salesChannelId);

        if ($limit < 1) {
            return new BlogCollection();
        }

        $categoryIds = $this->categoryIdsOf($blog);
        $exclude = [$blog->id];

        $criteria = new Criteria();
        $criteria->addFilter(
            $this->visibilityGuard->getVisibilityFilter(),
            new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsAnyFilter('id', $exclude)])
        );

        if ($categoryIds !== []) {
            $criteria->addFilter(new EqualsAnyFilter('categories.blogCategoryId', $categoryIds));
        }

        $criteria->addSorting(new FieldSorting('publishedAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);

        /** @var BlogCollection $related */
        $related = $this->blogRepository->search($criteria, $context)->getEntities();

        if ($related->count() >= $limit) {
            return $related;
        }

        $exclude = array_merge($exclude, array_values($related->getIds()));

        $fallback = new Criteria();
        $fallback->addFilter(
            $this->visibilityGuard->getVisibilityFilter(),
            new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsAnyFilter('id', $exclude)])
        );
        $fallback->addSorting(new FieldSorting('publishedAt', FieldSorting::DESCENDING));
        $fallback->setLimit($limit - $related->count());

        foreach ($this->blogRepository->search($fallback, $context)->getEntities() as $entry) {
            $related->add($entry);
        }

        return $related;
    }

    /**
     * The cover image of an entry, loaded by id because the entity carries no association to media.
     */
    private function coverMedia(BlogEntity $blog, Context $context): ?MediaEntity
    {
        $coverMediaId = $blog->coverMediaId;

        if ($coverMediaId === null) {
            return null;
        }

        $media = $this->mediaRepository->search(new Criteria([$coverMediaId]), $context)->getEntities()->first();

        return $media instanceof MediaEntity ? $media : null;
    }

    private function videoOf(BlogEntity $blog): ?BlogVideoEntity
    {
        foreach ($blog->videos ?? [] as $video) {
            if ($video instanceof BlogVideoEntity) {
                return $video;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function categoryIdsOf(BlogEntity $blog): array
    {
        $ids = [];

        $assignments = $blog->categories ?? [];

        foreach ($assignments as $assignment) {
            $ids[] = $assignment->blogCategoryId;
        }

        return $ids;
    }

    /**
     * The headings of the body, in document order, with the anchors the template has to render.
     *
     * The ids are positional (`section-1`, `section-2`) and not derived from the text: a chinese
     * headline has no latin slug, and a text derived anchor would silently collide between two
     * sections that share a word.
     *
     * @return list<array{level: int, id: string, title: string}>
     */
    private function tableOfContents(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $found = preg_match_all('/<h([23])\b[^>]*>(.*?)<\/h\1>/is', $content, $matches, \PREG_SET_ORDER);

        if ($found === false || $found === 0) {
            return [];
        }

        $toc = [];

        foreach ($matches as $index => $match) {
            $title = trim(html_entity_decode($this->htmlSanitizer->stripTags($match[2]), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

            if ($title === '') {
                continue;
            }

            $toc[] = [
                'level' => (int) $match[1],
                'id' => 'section-' . ($index + 1),
                'title' => $title,
            ];
        }

        return $toc;
    }

    /**
     * Injects the anchors of the table of contents into the body.
     *
     * The numbering has to match {@see self::tableOfContents()} exactly, which is why both walk the
     * same expression in the same order. A heading that already carries an id keeps it - an editor may
     * have linked to it from another page.
     */
    private function withAnchors(string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        $index = 0;

        $result = preg_replace_callback(
            '/<h([23])\b([^>]*)>(.*?)<\/h\1>/is',
            static function (array $match) use (&$index): string {
                ++$index;
                $attributes = $match[2];

                if (preg_match('/\bid\s*=/i', $attributes) === 1) {
                    return $match[0];
                }

                return \sprintf('<h%s%s id="section-%d">%s</h%s>', $match[1], $attributes, $index, $match[3], $match[1]);
            },
            $content
        );

        return $result ?? $content;
    }
}
