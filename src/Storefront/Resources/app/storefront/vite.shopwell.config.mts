import path from 'node:path';
import { defineConfig } from 'vite';
import { storefrontSbomPlugin } from './build/vite/storefront-sbom.mjs';

/**
 * Vite build config for the Shopwell runtime module.
 *
 * Produces a single ES module at Resources/public/storefront/shopwell/shopwell.js that:
 * - Exports ShopwellComponent and Shopwell as named ES module exports.
 * - Assigns both to window as a side effect for backward compatibility.
 */
export default defineConfig({
    plugins: [storefrontSbomPlugin({
        bundleName: 'Storefront',
        bundleRoot: path.resolve(import.meta.dirname, '../../..'),
        target: 'shopwell',
    })],
    build: {
        outDir: '../../public/storefront/shopwell',
        emptyOutDir: true,
        lib: {
            entry: './src/shopwell.ts',
            formats: ['es'],
            fileName: () => 'shopwell.js',
        },
    },
});
