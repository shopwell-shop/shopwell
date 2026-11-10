/**
 * @sw-package discovery
 */
import type { BlogTypeModule } from './blog.api.service';

/**
 * A lazy editor module or the name of a registered Administration component.
 *
 * @public
 */
export type BlogTypeLoader = () => Promise<BlogTypeModule | string>;

/**
 * Named editors use the Administration component factory, including plugin overrides.
 * The label may be a snippet key or display text.
 *
 * @public
 */
export type BlogTypeDefinition = {
    component: string;
    label?: string;
    icon?: string;
};

type Registration = {
    loader: BlogTypeLoader;
    definition?: BlogTypeDefinition;
};

const registry = new Map<string, Registration>();

/**
 * Register an editorial type during module boot.
 * Existing loaders remain supported; prefer a component name for factory extension support.
 * Registering an existing type replaces its editor and metadata.
 *
 * @public
 */
export function registerBlogType(type: string, editor: BlogTypeLoader | BlogTypeDefinition | string): void {
    if (type.trim() === '') {
        return;
    }

    if (typeof editor === 'function') {
        registry.set(type, { loader: editor });

        return;
    }

    const definition = typeof editor === 'string' ? { component: editor } : { ...editor };

    if (definition.component.trim() === '') {
        return;
    }

    registry.set(type, { loader: () => Promise.resolve(definition.component), definition });
}

/**
 * Return the lazy editor for a registered type, preserving the existing loader API.
 *
 * @public
 */
export function getBlogTypeComponent(type: string | null | undefined): BlogTypeLoader | undefined {
    return typeof type === 'string' ? registry.get(type)?.loader : undefined;
}

/**
 * Return optional display metadata for a named editor.
 *
 * @public
 */
export function getBlogTypeDefinition(type: string): BlogTypeDefinition | undefined {
    const definition = registry.get(type)?.definition;

    return definition ? { ...definition } : undefined;
}

/**
 * Return every registered type in stable alphabetical order.
 *
 * @public
 */
export function registeredBlogTypes(): string[] {
    return [...registry.keys()].sort((a, b) => a.localeCompare(b));
}

/**
 * Reset the registry between tests.
 *
 * @private
 */
export function resetBlogTypes(): void {
    registry.clear();
}
