/**
 * @sw-package checkout
 */
import { systemKey } from './device.helper';

describe('systemKey', () => {
    it.each(['⌘', 'Ctrl'])('uses the platform modifier %s', (key) => {
        expect(systemKey({ $device: { getSystemKey: () => key } })).toBe(key);
    });
});
