/**
 * @sw-package framework
 */

import useListing from 'src/app/composables/use-listing';

describe('test/_setup/setup-shopwell', () => {
    describe('Shopwell.Composables', () => {
        it('loads a composable when it is first read', () => {
            expect(Shopwell.Composables.useListing).toBe(useListing);
        });

        it('lets a spec spy on a composable and restore it', () => {
            const spy = jest.spyOn(Shopwell.Composables, 'useListing');

            expect(Shopwell.Composables.useListing).toBe(spy);

            spy.mockRestore();

            expect(Shopwell.Composables.useListing).toBe(useListing);
        });
    });
});
