/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */

import 'src/module/sw-export-channel-tracking';

const { Module } = Shopwell;

describe('src/module/sw-export-channel-tracking', () => {
    it('should be registered', () => {
        const module = Module.getModuleRegistry().get('sw-export-channel-tracking');
        expect(module).toBeTruthy();
    });

    it('should have snippets for en-GB and zh-CN', () => {
        const module = Module.getModuleRegistry().get('sw-export-channel-tracking');
        expect(typeof module.manifest.snippets?.['en-GB']).toBe('function');
        expect(typeof module.manifest.snippets?.['zh-CN']).toBe('function');
    });
});
