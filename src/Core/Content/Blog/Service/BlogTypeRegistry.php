<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Log\Package;

/**
 * The one place that answers "which handler owns this type".
 *
 * The handlers are injected as a tagged iterator, so a plugin that tags its own implementation is
 * part of the answer without touching the core. The registry is the only caller of `supports()`; the
 * rest of the code asks it for a handler and gets a typed answer or an exception, never a null that
 * every caller would have to think about.
 *
 * `find()` exists next to `get()` for the one caller that has to degrade instead of failing: the
 * storefront rendering an entry whose type came from an uninstalled plugin. A blank page is worse
 * than a generic layout, so the route falls back to the post handler while the administration and
 * every write path keep the loud `get()`.
 *
 * @final
 */
#[Package('discovery')]
class BlogTypeRegistry
{
    /**
     * @var list<BlogTypeHandlerInterface>
     */
    private readonly array $handlers;

    /**
     * @internal
     *
     * @param iterable<BlogTypeHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers)
    {
        $list = [];

        foreach ($handlers as $handler) {
            $list[] = $handler;
        }

        $this->handlers = $list;
    }

    /**
     * The handler of the type, or null when nobody claims it.
     */
    public function find(string $type): ?BlogTypeHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @throws BlogException when no registered handler claims the type
     */
    public function get(string $type): BlogTypeHandlerInterface
    {
        $handler = $this->find($type);

        if ($handler === null) {
            throw BlogException::unknownType($type);
        }

        return $handler;
    }
}
