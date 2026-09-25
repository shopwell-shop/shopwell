import initProductAnalytics from './telemetry.init';

describe('src/app/init-post/telemetry.init.ts', () => {
    it('calls Telemetry.init', async () => {
        jest.spyOn(Shopwell.Telemetry, 'initialize');

        await initProductAnalytics();

        expect(Shopwell.Telemetry.initialize).toHaveBeenCalled();
    });
});
