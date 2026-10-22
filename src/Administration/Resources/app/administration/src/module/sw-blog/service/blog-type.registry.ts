/**
 * @sw-package discovery
 *
 * The registry that decides which component edits the content of a blog entry.
 *
 * `blog.type` is a plain string and not an enum, so a plugin can bring its own type without a schema
 * change. The administration has to honour that: the detail page does not switch over "post" and
 * "video" itself, it asks this registry for a loader and falls back to the unknown component when
 * nobody registered one. That is what keeps a page written by a plugin editable in the same screen
 * as the two types of the core.
 *
 * The registry holds loaders, not components: a type is only downloaded when an entry of that type
 * is actually opened, so a plugin that brings a heavy editor does not slow down every blog page.
 */
import type { BlogTypeModule } from './blog.api.service';

/**
 * Loads the component of one type. It is a loader so the module is only downloaded when a page of
 * that type is opened.
 *
 * @private
 */
export type BlogTypeLoader = () => Promise<BlogTypeModule>;

const registry = new Map<string, BlogTypeLoader>();

/**
 * Registers the component for one type.
 *
 * A plugin calls this during its own boot, next to the `Shopwell.Component.register` of the
 * component it points at. Registering the same type twice replaces the loader, so a plugin may
 * override the editor of `post` on purpose.
 *
 * @private
 */
export function registerBlogType(type: string, loader: BlogTypeLoader): void {
    if (typeof type !== 'string' || type.trim() === '') {
        return;
    }

    registry.set(type, loader);
}

/**
 * The loader of one type, or undefined when nobody registered it.
 *
 * @private
 */
export function getBlogTypeComponent(type: string | null | undefined): BlogTypeLoader | undefined {
    if (typeof type !== 'string') {
        return undefined;
    }

    return registry.get(type);
}

/**
 * Every type the administration can currently edit, sorted so a filter dropdown is stable between
 * two requests.
 *
 * @private
 */
export function registeredBlogTypes(): string[] {
    return [...registry.keys()].sort((a, b) => a.localeCompare(b));
}

/**
 * Forgets every registration. Only used by the tests of the registry itself and of the host
 * component, so one test can not leak a type into the next.
 *
 * @private
 */
export function resetBlogTypes(): void {
    registry.clear();
}
