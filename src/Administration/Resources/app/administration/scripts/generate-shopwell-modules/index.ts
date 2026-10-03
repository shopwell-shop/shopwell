/**
 * @sw-package framework
 *
 * Writes the checked-in `shopwell:*` registry and declarations.
 *
 *     composer admin:generate-shopwell-modules
 *
 * Both outputs are checked in. `generate-shopwell-modules.spec.ts` fails when they drift from source.
 */

import fs from 'node:fs';
import path from 'node:path';
import { extractModuleRegistry } from './extract-modules';
import { renderDeclarations } from './render-declarations';
import type { ModuleRegistry } from '../../build/vite-plugins/virtual-shopwell-modules/definitions';

const administrationRoot = path.resolve(__dirname, '../..');

/** @private */
export const REGISTRY_FILE = path.join(administrationRoot, 'shopwell-modules.json');
/** @private */
export const DECLARATIONS_FILE = path.join(administrationRoot, 'src/shopwell-virtual-modules.d.ts');

/** @private Serialises the checked-in registry. */
export function renderRegistry(registry: ModuleRegistry): string {
    return `${JSON.stringify(registry, null, 4)}\n`;
}

function main(): void {
    const registry = extractModuleRegistry(administrationRoot);

    fs.writeFileSync(REGISTRY_FILE, renderRegistry(registry));
    fs.writeFileSync(DECLARATIONS_FILE, renderDeclarations(registry));

    // eslint-disable-next-line no-console
    console.log(
        `Wrote ${path.relative(administrationRoot, REGISTRY_FILE)} and ${path.relative(administrationRoot, DECLARATIONS_FILE)}.`,
    );
}

if (require.main === module) {
    main();
}
