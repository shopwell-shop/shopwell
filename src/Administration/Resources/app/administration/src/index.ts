/**
 * @sw-package framework
 */
import { ShopwellInstance } from 'src/core/shopware';

// IIFE
void (async () => {
    // Set the global Shopwell instance
    window.Shopwell = ShopwellInstance;

    if (window._swLoginOverrides) {
        window._swLoginOverrides.forEach((script) => {
            script();
        });
    }

    // Import the main file
    await import('src/app/main');

    // Start the main application
    window.startApplication();
})();
