<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a visitor submitted, before anything has been decided about it.
 *
 * The draft exists so the write service does not take nine positional arguments and so the route can
 * build it from a validated request bag without knowing which fields matter. It carries no status:
 * whether a comment is visible immediately is a policy of the sales channel and is resolved by the
 * service from {@see BlogSettings}, never taken from the request.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class BlogCommentDraft
{
    public function __construct(
        public readonly string $blogId,
        public readonly ?string $parentId,
        public readonly string $content,
        public readonly ?string $title,
        public readonly ?string $customerId,
        public readonly ?string $externalUser,
        public readonly ?string $externalEmail,
        public readonly string $salesChannelId,
        public readonly string $languageId,
    ) {
    }
}
