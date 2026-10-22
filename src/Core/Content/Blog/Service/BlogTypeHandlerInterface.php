<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a blog type has to answer so the storefront can render a page of that type.
 *
 * The core keeps the two answers that would otherwise be a growing `switch` over the type string in
 * the detail route and in the structured data: which template renders the page, and which schema.org
 * type describes it. A plugin adds a type by implementing this interface and tagging the service with
 * `shopwell.blog_type_handler` - the core never names the types itself.
 */
#[Package('discovery')]
interface BlogTypeHandlerInterface
{
    /**
     * Whether this handler is the one responsible for the given type.
     *
     * The registry asks every handler in turn, so an implementation must answer `false` for a type it
     * does not own and must never throw.
     */
    public function supports(string $type): bool;

    /**
     * The twig template that renders the detail page, e.g.
     * `@Storefront/storefront/page/blog/detail.html.twig`.
     */
    public function getDetailTemplate(): string;

    /**
     * The schema.org type of the page, e.g. `BlogPosting` or `VideoObject`, used by the structured
     * data the storefront renders into the head.
     */
    public function getSchemaType(): string;
}
