import { defineConfig } from 'vite';

/**
 * Vite build config for the Shopwell runtime module.
 *
 * Produces a single ES module at Resources/public/storefront/shopwell/shopwell.js that:
 * - Exports ShopwellComponent and Shopwell as named ES module exports.
 * - Assigns both to window as a side effect for backward compatibility.
 */
export default defineConfig({
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
