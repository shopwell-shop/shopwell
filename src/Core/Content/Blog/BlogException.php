<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything the blog domain refuses to do, as one typed exception.
 *
 * The codes are part of the contract: the storefront reacts to a code and never to the message, so a
 * message can be reworded without breaking a template.
 *
 * There is no "blog is disabled" error, because a blog has no such switch - an installation that does
 * not want a blog removes the routes or the content, not a flag that half the code would have to test.
 */
#[Package('discovery')]
class BlogException extends HttpException
{
    final public const BLOG_TYPE_UNKNOWN = 'CONTENT__BLOG_TYPE_UNKNOWN';
    final public const BLOG_NOT_FOUND = 'CONTENT__BLOG_NOT_FOUND';

    final public const COMMENT_PARENT_NOT_FOUND = 'CONTENT__BLOG_COMMENT_PARENT_NOT_FOUND';
    final public const COMMENT_PARENT_FOREIGN_BLOG = 'CONTENT__BLOG_COMMENT_PARENT_FOREIGN_BLOG';
    final public const COMMENT_PARENT_NOT_APPROVED = 'CONTENT__BLOG_COMMENT_PARENT_NOT_APPROVED';
    final public const COMMENT_DEPTH_EXCEEDED = 'CONTENT__BLOG_COMMENT_DEPTH_EXCEEDED';
    final public const COMMENT_FLOOD = 'CONTENT__BLOG_COMMENT_FLOOD';
    final public const COMMENT_DISABLED = 'CONTENT__BLOG_COMMENT_DISABLED';
    final public const SEO_URL_MAPPING_INVALID_ENTITY = 'CONTENT__BLOG_SEO_URL_MAPPING_INVALID_ENTITY';

    /**
     * An entry carries a type that no registered handler claims.
     *
     * This is a programming error rather than a request error: either the administration wrote a
     * type that has no handler, or the handler of a plugin was deactivated while its entries stayed.
     * It is answered with a 500 and not with a 404, because the row exists - the shop simply can not
     * render it, and swallowing that as "not found" would hide the broken installation.
     */
    public static function unknownType(string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BLOG_TYPE_UNKNOWN,
            'No handler is registered for the blog type "{{ type }}".',
            ['type' => $type]
        );
    }

    public static function notFound(string $blogId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::BLOG_NOT_FOUND,
            'The blog entry "{{ blogId }}" does not exist.',
            ['blogId' => $blogId]
        );
    }

    /**
     * The seo url mapping was asked to map something that is not a blog entity.
     *
     * A programming error, answered with a 500: the generator feeds the route the result of its own
     * criteria, so a foreign entity means a route was registered against the wrong definition.
     */
    public static function seoUrlMappingInvalidEntity(string $expected): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SEO_URL_MAPPING_INVALID_ENTITY,
            'The seo url mapping of "{{ expected }}" received an entity of another type.',
            ['expected' => $expected]
        );
    }

    /**
     * A reply points at a comment that does not exist. Answering with a 400 and not with a 404 is
     * deliberate: the request is the thing that is wrong, not the address the visitor opened.
     */
    public static function commentParentNotFound(string $parentId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COMMENT_PARENT_NOT_FOUND,
            'The comment "{{ parentId }}" that should be answered does not exist.',
            ['parentId' => $parentId]
        );
    }

    /**
     * A reply points at a comment of a different entry.
     *
     * Without this check a visitor could hang a reply below any comment of the shop and have it
     * rendered in a thread it does not belong to - the parent id travels in the request body and is
     * therefore under the control of the caller.
     */
    public static function commentParentForeignBlog(string $parentId, string $blogId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COMMENT_PARENT_FOREIGN_BLOG,
            'The comment "{{ parentId }}" does not belong to the blog entry "{{ blogId }}".',
            ['parentId' => $parentId, 'blogId' => $blogId]
        );
    }

    /**
     * A reply points at a comment a moderator has not approved, or has hidden again.
     *
     * Replying to something nobody can read would produce a thread that starts in the middle, so both
     * cases are refused with the same answer.
     */
    public static function commentParentNotApproved(string $parentId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COMMENT_PARENT_NOT_APPROVED,
            'The comment "{{ parentId }}" is not available for replies.',
            ['parentId' => $parentId]
        );
    }

    /**
     * The thread is deeper than the domain allows.
     *
     * The reply is rejected instead of being attached to the deepest allowed comment: silently
     * moving a reply would tell the author it succeeded while showing it in the wrong thread.
     */
    public static function commentDepthExceeded(int $maxDepth): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COMMENT_DEPTH_EXCEEDED,
            'A comment thread cannot be nested deeper than {{ maxDepth }} levels.',
            ['maxDepth' => $maxDepth]
        );
    }

    /**
     * The same address posted twice within the cool down.
     */
    public static function commentFlood(int $windowSeconds): self
    {
        return new self(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::COMMENT_FLOOD,
            'A comment was already written with this address. Try again in {{ windowSeconds }} seconds.',
            ['windowSeconds' => $windowSeconds]
        );
    }

    /**
     * The shop switched the comment thread off for this sales channel.
     *
     * It is a 403 and not a 404: the entry is there and readable, the shop simply does not accept
     * contributions below it. Hiding the form is not enough on its own - a form is markup, and the
     * route is the thing that decides.
     */
    public static function commentsDisabled(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::COMMENT_DISABLED,
            'Comments are not enabled for this sales channel.'
        );
    }
}
