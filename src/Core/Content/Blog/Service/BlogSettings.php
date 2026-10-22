<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * The blog settings of the shop, read in one place.
 *
 * Every default declared in `blog.xml` is repeated here on purpose: `shopwell.system_config` ships as
 * an empty array, so a key the shop never saved reads as `null` and not as its declared default. The
 * page sizes are the dangerous ones - an int fallback of `0` would produce an empty listing on every
 * installation that never opened the configuration, and nobody would connect the blank page to a
 * missing setting.
 *
 * There is no `enabled` key and there is no `feedLayout` key, and both absences are decisions:
 *
 * - a master switch would mean every route, every template and every api call has to ask "is the blog
 *   on" before doing anything, and the answer would be a 404 on a route that exists. A shop that does
 *   not run a blog does not ship the content;
 * - the density of the listing is not a shop wide property. Two categories of the same blog may want
 *   different densities, so the layout is an attribute of the cms element that renders the listing,
 *   with `classic` as the default the twig fallback uses as well.
 *
 * @final
 */
#[Package('discovery')]
class BlogSettings
{
    final public const CONFIG_KEY_POSTS_PER_PAGE = 'core.blog.postsPerPage';

    final public const CONFIG_KEY_SHOW_AUTHOR = 'core.blog.showAuthor';

    final public const CONFIG_KEY_SHOW_READING_TIME = 'core.blog.showReadingTime';

    final public const CONFIG_KEY_RELATED_POSTS_COUNT = 'core.blog.relatedPostsCount';

    final public const CONFIG_KEY_TOC_ENABLED = 'core.blog.tocEnabled';

    final public const CONFIG_KEY_COMMENTS_ENABLED = 'core.blog.commentsEnabled';

    final public const CONFIG_KEY_AUTO_PUBLISH_COMMENTS = 'core.blog.autoPublishComments';

    final public const CONFIG_KEY_COMMENTS_PER_PAGE = 'core.blog.commentsPerPage';

    final public const DEFAULT_POSTS_PER_PAGE = 12;

    final public const DEFAULT_SHOW_AUTHOR = true;

    final public const DEFAULT_SHOW_READING_TIME = true;

    final public const DEFAULT_RELATED_POSTS_COUNT = 3;

    final public const DEFAULT_TOC_ENABLED = true;

    final public const DEFAULT_COMMENTS_ENABLED = true;

    final public const DEFAULT_AUTO_PUBLISH_COMMENTS = false;

    final public const DEFAULT_COMMENTS_PER_PAGE = 10;

    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    /**
     * The page size of the listing. Never below one: a zero would render an empty first page.
     */
    public function getPostsPerPage(?string $salesChannelId = null): int
    {
        $value = $this->int(self::CONFIG_KEY_POSTS_PER_PAGE, $salesChannelId);

        if ($value === null || $value < 1) {
            return self::DEFAULT_POSTS_PER_PAGE;
        }

        return $value;
    }

    public function showAuthor(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_SHOW_AUTHOR, $salesChannelId);

        return $value === null ? self::DEFAULT_SHOW_AUTHOR : (bool) $value;
    }

    public function showReadingTime(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_SHOW_READING_TIME, $salesChannelId);

        return $value === null ? self::DEFAULT_SHOW_READING_TIME : (bool) $value;
    }

    /**
     * How many related entries the detail page shows. Zero switches the block off.
     */
    public function getRelatedPostsCount(?string $salesChannelId = null): int
    {
        $value = $this->int(self::CONFIG_KEY_RELATED_POSTS_COUNT, $salesChannelId);

        if ($value === null || $value < 0) {
            return self::DEFAULT_RELATED_POSTS_COUNT;
        }

        return $value;
    }

    public function isTocEnabled(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_TOC_ENABLED, $salesChannelId);

        return $value === null ? self::DEFAULT_TOC_ENABLED : (bool) $value;
    }

    /**
     * Whether the detail page offers the comment thread at all. Turning it off hides the thread and
     * the form, and the write route refuses as well - hiding the form alone would only stop the
     * honest visitors.
     */
    public function areCommentsEnabled(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_COMMENTS_ENABLED, $salesChannelId);

        return $value === null ? self::DEFAULT_COMMENTS_ENABLED : (bool) $value;
    }

    /**
     * Whether a new comment is visible without a moderator.
     *
     * The default is false: a shop that never opened the configuration gets moderation, which is the
     * failure mode that costs a moderator five minutes instead of the one that costs a shop its front
     * page.
     */
    public function autoPublishComments(?string $salesChannelId = null): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY_AUTO_PUBLISH_COMMENTS, $salesChannelId);

        return $value === null ? self::DEFAULT_AUTO_PUBLISH_COMMENTS : (bool) $value;
    }

    /**
     * How many top level comments one page of the thread shows. Replies come with their parent and
     * never count against this number - a page of ten threads may still hold a hundred replies.
     */
    public function getCommentsPerPage(?string $salesChannelId = null): int
    {
        $value = $this->int(self::CONFIG_KEY_COMMENTS_PER_PAGE, $salesChannelId);

        if ($value === null || $value < 1) {
            return self::DEFAULT_COMMENTS_PER_PAGE;
        }

        return $value;
    }

    private function int(string $key, ?string $salesChannelId): ?int
    {
        $value = $this->systemConfigService->get($key, $salesChannelId);

        return is_numeric($value) ? (int) $value : null;
    }
}
