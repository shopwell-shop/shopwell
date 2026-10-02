/**
 * @sw-package framework
 *
 * Renders the ambient declarations for every `shopwell:*` specifier.
 *
 * Each module declares the default and named exports recorded in the registry:
 *
 *     import debug, { warn } from 'shopwell:utils/debug';
 *
 * Types come from the same global branches and registry interfaces as the runtime values.
 */

import type { ModuleRegistry } from '../../build/vite-plugins/virtual-shopwell-modules/definitions';

const UTILS_MODULE = 'src/core/service/util.service';
const DATA_MODULE = 'src/core/data/index';
const COMPOSABLES_MODULE = 'src/app/composables/index';

/** @private The command shown in generated files and drift diagnostics. */
export const REGENERATE_COMMAND = 'composer admin:generate-shopwell-modules';

/**
 * The stability marker every specifier carries.
 *
 * Per block rather than once per file: an editor shows the doc comment nearest the declaration being
 * hovered, so a file-level one would reach the first module and silently leave the rest looking stable.
 */
const EXPERIMENTAL = '/** @experimental stableVersion:v6.8.0 */';

/** The composables keep the marker of their own sources, which stabilise a major later. */
const COMPOSABLES_EXPERIMENTAL = '/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */';

const FILE_HEADER = `/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0
 *
 * Types for the \`shopwell:*\` modules, which expose the global \`Shopwell\` object as ordinary
 * imports. \`build/vite-plugins/virtual-shopwell-modules\` generates their runtime counterpart from
 * the same \`shopwell-modules.json\`.
 *
 * Generated. Run \`${REGENERATE_COMMAND}\` after adding a utility, DAL class, composable, mixin, or store.
 */`;

function block(specifier: string, body: string[], stability = EXPERIMENTAL): string {
    return [
        stability,
        `declare module '${specifier}' {`,
        // A blank line keeps its emptiness: an indented one would fail the formatting check.
        ...body.map((line) => (line === '' ? '' : `    ${line}`)),
        '}',
        '',
    ].join('\n');
}

function namedExports(value: string, names: string[]): string[] {
    return names.map((name) => `export const ${name}: (typeof ${value})['${name}'];`);
}

/** A subpath of a branch that is itself an object, e.g. `shopwell:utils/debug`. */
function branchSubpath(specifier: string, branchModule: string, key: string, exports: string[], stability?: string): string {
    const body = [
        `import type branch from '${branchModule}';`,
        '',
        `const member: (typeof branch)['${key}'];`,
        '',
        'export default member;',
        ...namedExports('member', exports),
    ];

    return block(specifier, body, stability);
}

/** The root import of a branch, e.g. `shopwell:utils`, which publishes the whole branch and its members. */
function branchRoot(specifier: string, branchModule: string, exports: string[], stability?: string): string {
    const body = [
        `import type branch from '${branchModule}';`,
        '',
        'const members: typeof branch;',
        '',
        'export default members;',
        ...namedExports('members', exports),
    ];

    return block(specifier, body, stability);
}

function defaultOnlyModule(specifier: string, value: string, type: string): string {
    return block(specifier, [
        `const ${value}: ${type};`,
        '',
        `export default ${value};`,
    ]);
}

/** @private Renders all specifiers in registry order for deterministic diffs. */
export function renderDeclarations(registry: ModuleRegistry): string {
    const blocks: string[] = [];

    blocks.push(branchRoot('shopwell:utils', UTILS_MODULE, registry['shopwell:utils'].exports));
    Object.entries(registry['shopwell:utils'].subpaths).forEach(
        ([
            key,
            exports,
        ]) => blocks.push(branchSubpath(`shopwell:utils/${key}`, UTILS_MODULE, key, exports)),
    );

    blocks.push(branchRoot('shopwell:data', DATA_MODULE, registry['shopwell:data'].exports));
    Object.entries(registry['shopwell:data'].subpaths).forEach(
        ([
            key,
            exports,
        ]) => blocks.push(branchSubpath(`shopwell:data/${key}`, DATA_MODULE, key, exports)),
    );

    blocks.push(
        branchRoot(
            'shopwell:composables',
            COMPOSABLES_MODULE,
            registry['shopwell:composables'].exports,
            COMPOSABLES_EXPERIMENTAL,
        ),
    );
    Object.entries(registry['shopwell:composables'].subpaths).forEach(
        ([
            key,
            exports,
        ]) =>
            blocks.push(
                branchSubpath(`shopwell:composables/${key}`, COMPOSABLES_MODULE, key, exports, COMPOSABLES_EXPERIMENTAL),
            ),
    );

    Object.keys(registry['shopwell:mixins'].subpaths).forEach((key) =>
        blocks.push(defaultOnlyModule(`shopwell:mixins/${key}`, 'mixin', `MixinContainer['${key}']`)),
    );

    Object.keys(registry['shopwell:stores'].subpaths).forEach((key) =>
        blocks.push(defaultOnlyModule(`shopwell:stores/${key}`, 'useStore', `() => PiniaRootState['${key}']`)),
    );

    return [
        FILE_HEADER,
        '',
        '/* eslint-disable sw-deprecation-rules/private-feature-declarations -- Intentional public facade. */',
        '',
        ...blocks,
    ].join('\n');
}
