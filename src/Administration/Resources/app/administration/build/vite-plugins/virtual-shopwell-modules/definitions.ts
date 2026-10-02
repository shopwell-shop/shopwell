/**
 * @sw-package framework
 *
 * Shared resolution rules for the Vite modules and their Jest counterparts. The generated registry
 * decides which specifiers exist; the branch definitions below decide how each one reads `Shopwell`.
 */

import type { ShopwellClass } from 'src/core/shopwell';

/**
 * @private
 *
 * `Utils`, `Data` and `Composables` keep their exact `ShopwellClass` types. The registry methods accept
 * strings because their keys come from `shopwell-modules.json`.
 */
export type VirtualModuleGlobal = Pick<ShopwellClass, 'Utils' | 'Data' | 'Composables'> & {
    Mixin: { getByName: (name: string) => unknown };
    Store: { get: (id: string) => unknown };
};

/**
 * @private
 *
 * What one `shopwell:*` module family publishes.
 *
 * `exports` are the root import's named exports, empty for a family that has none. `subpaths` maps a
 * subpath key to the names it publishes alongside its default export; an empty list is default-only.
 */
export type ModuleRegistryEntry = {
    exports: string[];
    subpaths: Record<string, string[]>;
};

/** @private The checked-in registry of every `shopwell:*` specifier, keyed by family. */
export type ModuleRegistry = Record<string, ModuleRegistryEntry>;

/**
 * Each address keeps its generated expression next to its live-global reader. The parity test evaluates
 * every `emit` and compares it with the matching `read`.
 */
type Branch = {
    /**
     * The Shopwell version that added the branch to the global object. Only a branch newer than the
     * `shopwell:*` modules needs it: a prebuilt extension bundle can run on an Administration that has none.
     *
     * It covers the branch as a whole. A member added to the branch later still reads as `undefined` on
     * an older Administration that has the branch.
     */
    readonly since?: string;
    readonly root: {
        readonly emit: () => string;
        readonly read: (shopwell: VirtualModuleGlobal) => unknown;
    };
    readonly subpath: {
        readonly emit: (subpath: string) => string;
        readonly read: (shopwell: VirtualModuleGlobal, subpath: string) => unknown;
    };
};

function readOwn(branch: Record<string, unknown>, subpath: string, globalPath: string): unknown {
    if (!Object.hasOwn(branch, subpath)) {
        throw new Error(`"${subpath}" does not exist on ${globalPath}.`);
    }

    return branch[subpath];
}

const BRANCHES: Record<string, Branch> = {
    'shopwell:utils': {
        root: {
            emit: () => 'shopwell.Utils',
            read: (shopwell) => shopwell.Utils,
        },
        subpath: {
            emit: (subpath) => `shopwell.Utils[${JSON.stringify(subpath)}]`,
            read: (shopwell, subpath) => readOwn(shopwell.Utils, subpath, 'Shopwell.Utils'),
        },
    },
    'shopwell:data': {
        root: {
            emit: () => 'shopwell.Data',
            read: (shopwell) => shopwell.Data,
        },
        subpath: {
            emit: (subpath) => `shopwell.Data[${JSON.stringify(subpath)}]`,
            read: (shopwell, subpath) => readOwn(shopwell.Data, subpath, 'Shopwell.Data'),
        },
    },
    'shopwell:composables': {
        since: '6.7.16.0',
        root: {
            emit: () => 'shopwell.Composables',
            read: (shopwell) => shopwell.Composables,
        },
        subpath: {
            emit: (subpath) => `shopwell.Composables[${JSON.stringify(subpath)}]`,
            read: (shopwell, subpath) => readOwn(shopwell.Composables, subpath, 'Shopwell.Composables'),
        },
    },
    'shopwell:mixins': {
        root: {
            emit: () => 'shopwell.Mixin',
            read: (shopwell) => shopwell.Mixin,
        },
        subpath: {
            // Annotated pure so Rollup drops the lookup when the importer's binding is unused.
            emit: (subpath) => `/*@__PURE__*/ shopwell.Mixin.getByName(${JSON.stringify(subpath)})`,
            read: (shopwell, subpath) => shopwell.Mixin.getByName(subpath),
        },
    },
    'shopwell:stores': {
        root: {
            emit: () => 'shopwell.Store',
            read: (shopwell) => shopwell.Store,
        },
        subpath: {
            // A store is looked up per call, so importing never depends on it being registered yet.
            emit: (subpath) => `() => shopwell.Store.get(${JSON.stringify(subpath)})`,
            read: (shopwell, subpath) => () => shopwell.Store.get(subpath),
        },
    },
};

/** @private The module families, e.g. `shopwell:utils`. */
export const MODULE_FAMILIES = Object.keys(BRANCHES);

/** @private A parsed `shopwell:*` import: the family and optional subpath key. */
export type ParsedSpecifier = {
    readonly family: string;
    /** The subpath, or `undefined` for a root import of the family. */
    readonly subpath?: string;
};

/**
 * @private
 *
 * Splits a `shopwell:*` import into its family and subpath.
 *
 * `specifier` includes the prefix, for example `shopwell:utils` or `shopwell:mixins/myCoolMixin`.
 *
 * Returns `undefined` for a specifier no family serves. Whether the parsed specifier actually resolves
 * is `exportNames`' answer, not this one: a family may publish no root import. The first slash separates
 * the family from the complete subpath.
 */
export function parseSpecifier(specifier: string): ParsedSpecifier | undefined {
    const separator = specifier.indexOf('/');

    if (separator === -1) {
        return Object.hasOwn(BRANCHES, specifier) ? { family: specifier } : undefined;
    }

    const family = specifier.slice(0, separator);
    const subpath = specifier.slice(separator + 1);

    return Object.hasOwn(BRANCHES, family) && subpath.length > 0 ? { family, subpath } : undefined;
}

/**
 * @private
 *
 * The export names a specifier publishes: the root import's members, or one subpath's own names.
 *
 * `undefined` means the specifier does not resolve: an unknown key, or a root import of a family that
 * publishes no root exports. An empty list means the opposite — it resolves, publishing its default
 * export alone.
 */
export function exportNames(registry: ModuleRegistry, parsed: ParsedSpecifier): string[] | undefined {
    if (!Object.hasOwn(registry, parsed.family)) {
        return undefined;
    }

    const entry = registry[parsed.family];

    if (parsed.subpath === undefined) {
        return entry.exports.length > 0 ? entry.exports : undefined;
    }

    return Object.hasOwn(entry.subpaths, parsed.subpath) ? entry.subpaths[parsed.subpath] : undefined;
}

/** @private Every root and subpath specifier published by the registry. */
export function allSpecifiers(registry: ModuleRegistry): string[] {
    return Object.entries(registry).flatMap(
        ([
            family,
            entry,
        ]) => [
            ...(exportNames(registry, { family }) ? [family] : []),
            ...Object.keys(entry.subpaths).map((key) => `${family}/${key}`),
        ],
    );
}

/**
 * @private
 *
 * The statements an extension module runs before it reads a branch that older Administrations lack.
 * Without them, reading a member of the missing branch fails with a bare TypeError.
 *
 * Only a plugin bundle can get here, and the plugin lifecycle enforces its composer.json requirements,
 * so the message names that fix. The installed version is in the context, because the plugin bundles
 * are loaded from the same config response.
 */
export function branchGuard(parsed: ParsedSpecifier): string[] {
    const since = Object.hasOwn(BRANCHES, parsed.family) ? BRANCHES[parsed.family].since : undefined;

    if (since === undefined) {
        return [];
    }

    const required = `"${parsed.family}" requires Shopwell ${since} or later, but the installed version is `;
    const fix = `. Require shopwell/administration >=${since} in the composer.json of the extension.`;

    return [
        `if (!${BRANCHES[parsed.family].root.emit()}) {`,
        `    throw new Error(${JSON.stringify(required)} + shopwell.Context?.app?.config?.version + ${JSON.stringify(fix)});`,
        '}',
    ];
}

/** @private The generated expression for a specifier's default export. */
export function defaultExpression(parsed: ParsedSpecifier): string | undefined {
    if (!Object.hasOwn(BRANCHES, parsed.family)) {
        return undefined;
    }

    const branch = BRANCHES[parsed.family];

    return parsed.subpath === undefined ? branch.root.emit() : branch.subpath.emit(parsed.subpath);
}

/** @private The generated expression for one named export. */
export function memberExpression(parsed: ParsedSpecifier, member: string): string {
    const moduleExpression = defaultExpression(parsed);

    return `${moduleExpression}[${JSON.stringify(member)}]`;
}

/**
 * @private
 *
 * Resolves one export of a `shopwell:*` module against a live global object.
 *
 * The Jest shims call this because a resolver hands them a property name rather than generated code:
 * they need the value the generated module would have exported for it.
 */
export function resolveVirtualExport(
    registry: ModuleRegistry,
    specifier: string,
    exportName: string,
    shopwell: VirtualModuleGlobal,
): unknown {
    const parsed = parseSpecifier(specifier);
    const branch = parsed && BRANCHES[parsed.family];

    if (!parsed || !branch || !exportNames(registry, parsed)) {
        throw new Error(`"${specifier}" is not a Shopwell virtual module.`);
    }

    // A root import's default export is the whole branch. Its named exports are the branch's members,
    // and every member is also a subpath.
    if (parsed.subpath === undefined) {
        return exportName === 'default' ? branch.root.read(shopwell) : branch.subpath.read(shopwell, exportName);
    }

    const moduleValue = branch.subpath.read(shopwell, parsed.subpath);

    if (exportName === 'default') {
        return moduleValue;
    }

    if (moduleValue === null || typeof moduleValue !== 'object' || !Object.hasOwn(moduleValue, exportName)) {
        throw new Error(`"${specifier}" has no export "${exportName}".`);
    }

    return (moduleValue as Record<string, unknown>)[exportName];
}
