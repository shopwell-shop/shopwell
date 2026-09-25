/**
 * @sw-package framework
 */
describe('directives/click-outside', () => {
    it('should register the directive', () => {
        expect(Shopwell.Directive.getByName('click-outside')).toBeDefined();
    });
});
