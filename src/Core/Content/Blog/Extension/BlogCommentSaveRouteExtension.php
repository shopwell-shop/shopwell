<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * The extension point of the comment write route.
 *
 * A plugin subscribes to `BlogCommentSaveRouteExtension::onPre()` to inspect or replace the write, and
 * to `onPost()` to react to the result. The extension wraps the whole route body, which is why the
 * outer method contains nothing but the dispatch: everything that decides about a comment stays in one
 * private method that a listener can short-circuit wholesale.
 *
 * The properties are public and mutable on purpose - a moderation plugin replaces the data bag or
 * stops the propagation without the route having to expose a setter for each of them.
 *
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
#[Package('discovery')]
final class BlogCommentSaveRouteExtension extends Extension
{
    public const NAME = 'blog-comment-save-route.save';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $data,
        public readonly SalesChannelContext $context,
    ) {
    }
}
