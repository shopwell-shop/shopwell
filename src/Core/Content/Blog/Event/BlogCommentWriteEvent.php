<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Event;

use Shopwell\Core\Content\Blog\Service\BlogCommentDraft;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched right before a comment is written, with the draft the route built from the request.
 *
 * This is the extension point of the write side. A moderation plugin listens here to refuse a comment,
 * a spam filter to reject an address, an anti duplicate check to compare against recent posts. It
 * replaces the abstract route object the first draft used: a decorator forces every shop to recompile
 * the container and to re-implement the method it wants to extend, while a listener is registered,
 * ordered and removed without touching the route.
 *
 * The draft is immutable on purpose. An extension that could rewrite the body would be a second write
 * path whose result nobody can reproduce from the request; the event is a veto and a notification, not
 * a transformer.
 *
 * Throwing refuses the write. The kernel turns a domain exception into the matching http status, and
 * the storefront form shows it as a flash message instead of a half written comment.
 */
#[Package('discovery')]
final class BlogCommentWriteEvent extends Event
{
    public const EVENT_NAME = 'blog_comment.write';

    /**
     * @internal
     */
    public function __construct(
        private readonly BlogCommentDraft $draft,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getDraft(): BlogCommentDraft
    {
        return $this->draft;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
