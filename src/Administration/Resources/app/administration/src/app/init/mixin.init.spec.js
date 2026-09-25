/**
 * @sw-package framework
 */
import createAppMixin from 'src/app/init/mixin.init';

describe('src/app/init/mixin.init.js', () => {
    it('should register all app mixins', () => {
        createAppMixin();

        expect(Shopwell.Mixin.getByName('sw-form-field')).toBeDefined();
        expect(Shopwell.Mixin.getByName('generic-condition')).toBeDefined();
        expect(Shopwell.Mixin.getByName('listing')).toBeDefined();
        expect(Shopwell.Mixin.getByName('notification')).toBeDefined();
        expect(Shopwell.Mixin.getByName('placeholder')).toBeDefined();
        expect(Shopwell.Mixin.getByName('position')).toBeDefined();
        expect(Shopwell.Mixin.getByName('remove-api-error')).toBeDefined();
        expect(Shopwell.Mixin.getByName('ruleContainer')).toBeDefined();
        expect(Shopwell.Mixin.getByName('salutation')).toBeDefined();
        expect(Shopwell.Mixin.getByName('sw-inline-snippet')).toBeDefined();
        expect(Shopwell.Mixin.getByName('translate-with-fallback')).toBeDefined();
        expect(Shopwell.Mixin.getByName('user-settings')).toBeDefined();
        expect(Shopwell.Mixin.getByName('validation')).toBeDefined();
    });
});
