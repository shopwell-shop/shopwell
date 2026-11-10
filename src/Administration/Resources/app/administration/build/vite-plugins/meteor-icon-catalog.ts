/** @sw-package framework */
import { readdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';

/** Resolves names at build time; icon SVG contents remain loaded on demand by mt-icon. */
/** @private */
export function meteorIconNames(): string[] {
    const root = path.dirname(createRequire(__filename).resolve('@shopwell-ag/meteor-icon-kit/package.json'));
    return ['regular', 'solid']
        .flatMap((mode) =>
            readdirSync(path.join(root, 'icons', mode))
                .filter((file) => file.endsWith('.svg'))
                .map((file) => `${mode}-${file.slice(0, -4)}`),
        )
        .sort();
}
