/**
 * @sw-package framework
 */
describe('src/app/filter/file-size.filter.js', () => {
    const fileSizeFilter = Shopwell.Filter.getByName('fileSize');

    Shopwell.Utils.format.fileSize = jest.fn();

    beforeEach(() => {
        Shopwell.Utils.format.fileSize.mockClear();
    });

    it('should contain a filter', () => {
        expect(fileSizeFilter).toBeDefined();
    });

    it('should return empty string when no value is given', () => {
        expect(fileSizeFilter()).toBe('');
    });

    it('should call the fileSize format util for formatting', () => {
        fileSizeFilter(1856165, {
            myLocaleOptions: 'foo',
        });

        expect(Shopwell.Utils.format.fileSize).toHaveBeenCalledWith(1856165, {
            myLocaleOptions: 'foo',
        });
    });
});
