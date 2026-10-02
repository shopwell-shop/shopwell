/**
 * @sw-package framework
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import VirtualShopwellModulesPlugin, { exportNames, generateModuleSource, readRegistry } from './index';
import { parseSpecifier } from './definitions';
import type { ModuleRegistry } from './definitions';

const administrationRoot = path.resolve(__dirname, '../../..');
const registry = readRegistry(administrationRoot);

type PluginHooks = {
    resolveId: (id: string) => string | null;
    load: (this: { addWatchFile: (file: string) => void }, id: string) => string | null;
    handleHotUpdate: (context: {
        file: string;
        server: {
            moduleGraph: {
                idToModuleMap: Map<string, { id: string | null }>;
                invalidateModule: (module: { id: string | null }) => void;
            };
        };
    }) => Array<{ id: string | null }> | undefined;
};

function createPlugin(root = administrationRoot): PluginHooks {
    return VirtualShopwellModulesPlugin({ administrationRoot: root, consumer: 'host' }) as unknown as PluginHooks;
}

function writeRegistry(root: string, exportName: string): string {
    const registryFile = path.join(root, 'shopwell-modules.json');
    const currentRegistry: ModuleRegistry = {
        'shopwell:utils': {
            exports: [exportName],
            subpaths: { [exportName]: [] },
        },
    };

    fs.writeFileSync(registryFile, JSON.stringify(currentRegistry));

    return registryFile;
}

/** `load` is called with Rollup's plugin context; only `addWatchFile` is used. */
function load(plugin: PluginHooks, id: string): { source: string | null; watched: string[] } {
    const watched: string[] = [];
    const source = plugin.load.call({ addWatchFile: (file) => watched.push(file) }, id);

    return { source, watched };
}

describe('build/vite-plugins/virtual-shopwell-modules', () => {
    it('is a plugin named shopwell-virtual-modules', () => {
        expect(VirtualShopwellModulesPlugin({ administrationRoot, consumer: 'host' }).name).toBe('shopwell-virtual-modules');
    });

    describe('resolveId', () => {
        it.each([
            'shopwell:utils',
            'shopwell:data',
            'shopwell:utils/debug',
            'shopwell:data/Criteria',
            'shopwell:mixins/sw-form-field',
            'shopwell:stores/notification',
        ])('claims %s', (specifier) => {
            expect(createPlugin().resolveId(specifier)).toBe(`\0${specifier}`);
        });

        it('refuses a key the registry does not list', () => {
            const plugin = createPlugin();

            expect(plugin.resolveId('shopwell:utils/notAUtil')).toBeNull();
            expect(plugin.resolveId('shopwell:stores/notARegisteredStore')).toBeNull();
            expect(plugin.resolveId('shopwell:mixins/notAMixin')).toBeNull();
            expect(plugin.resolveId('shopwell:utils/constructor')).toBeNull();
            expect(plugin.resolveId('shopwell:utils/toString')).toBeNull();
            expect(plugin.resolveId('shopwell:utils/__proto__')).toBeNull();
        });

        it('refuses a bare import of the registry-backed families', () => {
            const plugin = createPlugin();

            expect(plugin.resolveId('shopwell:mixins')).toBeNull();
            expect(plugin.resolveId('shopwell:stores')).toBeNull();
        });

        it('leaves every other import alone', () => {
            const plugin = createPlugin();

            expect(plugin.resolveId('src/core/service/util.service')).toBeNull();
            expect(plugin.resolveId('vue')).toBeNull();
        });
    });

    describe('load', () => {
        it('serves a specifier under its resolved id and watches the registry', () => {
            const { source, watched } = load(createPlugin(), '\0shopwell:utils/debug');

            expect(source).toBe(generateModuleSource('shopwell:utils/debug', registry));
            expect(watched).toEqual([path.join(administrationRoot, 'shopwell-modules.json')]);
        });

        it('leaves ids it did not resolve alone', () => {
            const plugin = createPlugin();

            expect(load(plugin, 'src/core/shopwell.ts').source).toBeNull();
            expect(load(plugin, '\0other-plugin:thing').source).toBeNull();
        });
    });

    describe('handleHotUpdate', () => {
        it('reloads the registry and invalidates loaded virtual modules', () => {
            const root = fs.mkdtempSync(path.join(os.tmpdir(), 'shopwell-virtual-modules-'));

            try {
                const registryFile = writeRegistry(root, 'first');

                const plugin = createPlugin(root);
                const virtualModule = { id: '\0shopwell:utils' };
                const otherVirtualModule = { id: '\0other-plugin:thing' };
                const unrelatedModule = { id: '/src/main.ts' };
                const invalidateModule = jest.fn();
                const idToModuleMap = new Map(
                    [
                        virtualModule,
                        otherVirtualModule,
                        unrelatedModule,
                    ].map((module) => [
                        module.id,
                        module,
                    ]),
                );

                expect(plugin.resolveId('shopwell:utils')).toBe('\0shopwell:utils');
                expect(load(plugin, '\0shopwell:utils').source).toContain('export const first');

                writeRegistry(root, 'second');

                const updatedModules = plugin.handleHotUpdate({
                    file: registryFile,
                    server: {
                        moduleGraph: {
                            idToModuleMap,
                            invalidateModule,
                        },
                    },
                });

                expect(updatedModules).toEqual([virtualModule]);
                expect(invalidateModule).toHaveBeenCalledTimes(1);
                expect(invalidateModule).toHaveBeenCalledWith(virtualModule);
                expect(plugin.resolveId('shopwell:utils/first')).toBeNull();
                expect(plugin.resolveId('shopwell:utils/second')).toBe('\0shopwell:utils/second');
                expect(load(plugin, '\0shopwell:utils').source).toContain('export const second');
            } finally {
                fs.rmSync(root, { recursive: true, force: true });
            }
        });
    });

    describe('generateModuleSource', () => {
        it('gives a root import one binding per member plus the branch as default', () => {
            const source = generateModuleSource('shopwell:utils', registry) as string;

            registry['shopwell:utils'].exports.forEach((member) => {
                expect(source).toContain(`export const ${member} = shopwell.Utils["${member}"];`);
            });

            expect(source).toContain('export default shopwell.Utils;');
        });

        it('gives a namespace subpath its own members plus the namespace as default', () => {
            const source = generateModuleSource('shopwell:utils/debug', registry) as string;

            expect(source).toContain('export const warn = shopwell.Utils["debug"]["warn"];');
            expect(source).toContain('export const error = shopwell.Utils["debug"]["error"];');
            expect(source).toContain('export default shopwell.Utils["debug"];');
        });

        it('gives a mixin subpath a single pure lookup, so an unused import resolves nothing', () => {
            const source = generateModuleSource('shopwell:mixins/sw-form-field', registry) as string;

            expect(source).toContain('export default /*@__PURE__*/ shopwell.Mixin.getByName("sw-form-field");');
            expect(source.match(/getByName/g)).toHaveLength(1);
        });

        it('gives a store subpath a composable, so the lookup happens per call', () => {
            const source = generateModuleSource('shopwell:stores/notification', registry) as string;

            expect(source).toContain('export default () => shopwell.Store.get("notification");');
        });

        it('imports the instance for the host, so evaluation order settles when it exists', () => {
            const source = generateModuleSource('shopwell:data/Criteria', registry, 'host') as string;

            expect(source).toContain("import { ShopwellInstance as shopwell } from 'src/core/shopwell';");
            expect(source).not.toContain('globalThis.Shopwell');
        });

        it('imports the mixin registry too, so the lookup cannot run before registration', () => {
            const source = generateModuleSource('shopwell:mixins/sw-form-field', registry, 'host') as string;

            expect(source).toContain("import 'src/app/mixin';");
        });

        it('exports the composables of the instance', () => {
            const source = generateModuleSource('shopwell:composables', registry, 'host') as string;

            expect(source).toContain('export const useListing = shopwell.Composables["useListing"];');
            expect(source).toContain('export default shopwell.Composables;');
        });

        it('gives a composable subpath the composable as its only export', () => {
            const source = generateModuleSource('shopwell:composables/useListing', registry, 'host') as string;

            expect(source).toContain('export default shopwell.Composables["useListing"];');
            expect(source).not.toContain('export const');
        });

        it('reads the global for an extension, which has no Administration source to import', () => {
            const source = generateModuleSource('shopwell:data/Criteria', registry, 'extension') as string;

            expect(source).toContain('const shopwell = globalThis.Shopwell;');
            expect(source).not.toContain("from 'src/core/shopwell'");
            expect(source).toContain('should be unreachable');
            expect(source).not.toContain('requires Shopwell');
        });

        it.each([
            'shopwell:composables',
            'shopwell:composables/useListing',
        ])('checks for the branch before an extension reads %s, which older Administrations lack', (specifier) => {
            const source = generateModuleSource(specifier, registry, 'extension') as string;

            expect(source).toMatch(/if \(!shopwell\.Composables\) \{[\s\S]*requires Shopwell 6\.7\.16\.0[\s\S]*export/);
        });

        it('leaves the branch check out of the host, which imports its own instance', () => {
            const source = generateModuleSource('shopwell:composables', registry, 'host') as string;

            expect(source).not.toContain('requires Shopwell');
        });

        it('returns nothing for a specifier the registry does not list', () => {
            expect(generateModuleSource('shopwell:nope', registry)).toBeUndefined();
            expect(generateModuleSource('shopwell:mixins/notAMixin', registry)).toBeUndefined();
        });
    });

    describe('exportNames', () => {
        it('separates a missing key from a key with no named exports', () => {
            const mixin = parseSpecifier('shopwell:mixins/sw-form-field');
            const missing = parseSpecifier('shopwell:mixins/notAMixin');

            expect(exportNames(registry, mixin!)).toEqual([]);
            expect(exportNames(registry, missing!)).toBeUndefined();
        });
    });
});
