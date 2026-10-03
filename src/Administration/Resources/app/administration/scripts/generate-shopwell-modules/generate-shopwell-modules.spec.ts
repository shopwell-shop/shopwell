/**
 * @sw-package framework
 *
 * The `shopwell:*` registry and its declarations are checked in, so a new specifier shows up in a pull
 * request diff instead of appearing at build time. That only holds while the files match the sources.
 */

import fs from 'node:fs';
import path from 'node:path';
import { extractModuleRegistry } from './extract-modules';
import { renderDeclarations, REGENERATE_COMMAND } from './render-declarations';
import { renderRegistry, REGISTRY_FILE, DECLARATIONS_FILE } from './index';

const administrationRoot = path.resolve(__dirname, '../..');
const registry = extractModuleRegistry(administrationRoot);

describe('scripts/generate-shopwell-modules', () => {
    it('has a checked-in registry that matches the Administration sources', () => {
        expect(
            fs.readFileSync(REGISTRY_FILE, 'utf8'),
            `shopwell-modules.json is stale. Run \`${REGENERATE_COMMAND}\`.`,
        ).toBe(renderRegistry(registry));
    });

    it('has checked-in declarations that match the registry', () => {
        expect(
            fs.readFileSync(DECLARATIONS_FILE, 'utf8'),
            `src/shopwell-virtual-modules.d.ts is stale. Run \`${REGENERATE_COMMAND}\`.`,
        ).toBe(renderDeclarations(registry));
    });

    describe('the registry it builds', () => {
        it('gives the branch-backed families root exports and the registry-backed ones none', () => {
            expect(registry['shopwell:utils'].exports.length).toBeGreaterThan(0);
            expect(registry['shopwell:data'].exports.length).toBeGreaterThan(0);
            expect(registry['shopwell:composables'].exports.length).toBeGreaterThan(0);
            expect(registry['shopwell:mixins'].exports).toEqual([]);
            expect(registry['shopwell:stores'].exports).toEqual([]);
        });

        it('makes every root export a subpath of its own', () => {
            expect(Object.keys(registry['shopwell:utils'].subpaths)).toEqual(registry['shopwell:utils'].exports);
            expect(Object.keys(registry['shopwell:data'].subpaths)).toEqual(registry['shopwell:data'].exports);
            expect(Object.keys(registry['shopwell:composables'].subpaths)).toEqual(registry['shopwell:composables'].exports);
        });

        it('reads the utility namespaces that can be destructured', () => {
            expect(registry['shopwell:utils'].subpaths.debug).toEqual([
                'warn',
                'error',
            ]);
            expect(registry['shopwell:utils'].subpaths.createId).toEqual([]);
        });

        it('publishes only the mixins the central registry owns', () => {
            expect(Object.keys(registry['shopwell:mixins'].subpaths)).toContain('sw-form-field');
            expect(Object.keys(registry['shopwell:mixins'].subpaths)).not.toContain('cms-element');
            expect(Object.keys(registry['shopwell:stores'].subpaths)).toContain('notification');
        });
    });

    describe('the declarations it renders', () => {
        const declarations = renderDeclarations(registry);

        it('lists only the named exports recorded for a namespace subpath', () => {
            expect(declarations).toContain("declare module 'shopwell:utils/debug'");
            expect(declarations).toContain("export const warn: (typeof member)['warn'];");
            expect(declarations).toContain("export const error: (typeof member)['error'];");
        });

        it('gives a default-only subpath no named exports', () => {
            const criteriaDeclaration = declarations.slice(
                declarations.indexOf("declare module 'shopwell:data/Criteria'"),
                declarations.indexOf("declare module 'shopwell:data/Entity'"),
            );

            expect(criteriaDeclaration).toContain('export default member;');
            expect(criteriaDeclaration).not.toContain('export const');
        });

        it('marks the composables with the stability of their own sources', () => {
            expect(declarations).toContain(
                [
                    '/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */',
                    "declare module 'shopwell:composables' {",
                ].join('\n'),
            );
            expect(declarations).toContain(
                [
                    '/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */',
                    "declare module 'shopwell:composables/useListing' {",
                ].join('\n'),
            );
            expect(declarations).toContain(
                [
                    '/** @experimental stableVersion:v6.8.0 */',
                    "declare module 'shopwell:data' {",
                ].join('\n'),
            );
        });
    });
});
