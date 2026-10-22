<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogVideo\BlogVideoEntity;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * Everything one article needs, resolved and ready to render - and nothing that belongs to the
 * storefront.
 *
 * The struct exists so the query side can be written without a page object: `BlogDetailReader` fills
 * it, the storefront turns it into a page and attaches the meta information, and a headless consumer
 * can read the same struct without an http request being involved in the layout decision.
 *
 * The cms layout is deliberately absent. Resolving it needs the request and the sales channel context,
 * and a struct that answered "which page renders this" would be a page loader in disguise.
 */
#[Package('discovery')]
class BlogDetailStruct extends Struct
{
    protected ?BlogEntity $blog = null;

    protected ?BlogCollection $relatedBlogs = null;

    protected ?BlogAuthorEntity $author = null;

    protected ?MediaEntity $coverMedia = null;

    protected ?BlogVideoEntity $video = null;

    /**
     * The table of contents, one entry per h2/h3 of the body:
     * `[['level' => 2, 'id' => 'section-1', 'title' => 'A headline'], ...]`.
     *
     * @var list<array{level: int, id: string, title: string}>
     */
    protected array $toc = [];

    /**
     * The body with the anchors of the table of contents already injected. A template renders this and
     * not `blog.content`, so a link in the toc always finds its heading - injecting the ids in
     * javascript instead would leave the toc dead for a reader without it.
     */
    protected string $content = '';

    protected int $readingTimeMinutes = 1;

    protected bool $showAuthor = true;

    protected bool $showReadingTime = true;

    protected bool $tocEnabled = true;

    /**
     * The comment thread of the current page, already built into trees. A template walks it
     * recursively and never groups by `parentId` itself - grouping in twig is a quadratic loop over a
     * list the reader already had to load in full.
     */
    protected ?BlogCommentTreeResult $comments = null;

    /**
     * Whether the shop offers the thread at all. Off means: no thread, no form, and the write route
     * refuses too - hiding the form alone would only stop the honest visitors.
     */
    protected bool $commentsEnabled = true;

    /**
     * Whether a new comment appears without a moderator.
     */
    protected bool $commentsAutoPublish = false;

    protected int $commentsPerPage = 10;

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(BlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getRelatedBlogs(): ?BlogCollection
    {
        return $this->relatedBlogs;
    }

    public function setRelatedBlogs(BlogCollection $relatedBlogs): void
    {
        $this->relatedBlogs = $relatedBlogs;
    }

    public function getAuthor(): ?BlogAuthorEntity
    {
        return $this->author;
    }

    public function setAuthor(?BlogAuthorEntity $author): void
    {
        $this->author = $author;
    }

    public function getCoverMedia(): ?MediaEntity
    {
        return $this->coverMedia;
    }

    public function setCoverMedia(?MediaEntity $coverMedia): void
    {
        $this->coverMedia = $coverMedia;
    }

    /**
     * The video of a video entry, null for every other type.
     */
    public function getVideo(): ?BlogVideoEntity
    {
        return $this->video;
    }

    public function setVideo(?BlogVideoEntity $video): void
    {
        $this->video = $video;
    }

    /**
     * @return list<array{level: int, id: string, title: string}>
     */
    public function getToc(): array
    {
        return $this->toc;
    }

    /**
     * @param list<array{level: int, id: string, title: string}> $toc
     */
    public function setToc(array $toc): void
    {
        $this->toc = $toc;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getReadingTimeMinutes(): int
    {
        return $this->readingTimeMinutes;
    }

    public function setReadingTimeMinutes(int $readingTimeMinutes): void
    {
        $this->readingTimeMinutes = $readingTimeMinutes;
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

    public function getTocEnabled(): bool
    {
        return $this->tocEnabled;
    }

    public function setTocEnabled(bool $tocEnabled): void
    {
        $this->tocEnabled = $tocEnabled;
    }

    public function getComments(): ?BlogCommentTreeResult
    {
        return $this->comments;
    }

    public function setComments(BlogCommentTreeResult $comments): void
    {
        $this->comments = $comments;
    }

    public function getCommentsEnabled(): bool
    {
        return $this->commentsEnabled;
    }

    public function setCommentsEnabled(bool $commentsEnabled): void
    {
        $this->commentsEnabled = $commentsEnabled;
    }

    public function getCommentsAutoPublish(): bool
    {
        return $this->commentsAutoPublish;
    }

    public function setCommentsAutoPublish(bool $commentsAutoPublish): void
    {
        $this->commentsAutoPublish = $commentsAutoPublish;
    }

    public function getCommentsPerPage(): int
    {
        return $this->commentsPerPage;
    }

    public function setCommentsPerPage(int $commentsPerPage): void
    {
        $this->commentsPerPage = $commentsPerPage;
    }

    public function getApiAlias(): string
    {
        return 'blog_detail';
    }
}
