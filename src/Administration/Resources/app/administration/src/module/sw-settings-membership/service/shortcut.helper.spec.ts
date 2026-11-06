/**
 * @sw-package checkout
 */
import { shortcutAllowsSave } from './shortcut.helper';

describe('shortcutAllowsSave', () => {
    it.each([
        [{ allowSave: true }, true],
        [{ allowSave: false }, false],
        [{}, false],
    ])('enables the shortcut only when saving is allowed', (component, allowed) => {
        expect(shortcutAllowsSave(component)).toBe(allowed);
    });
});
