import { mount } from '@vue/test-utils';
import SwSettingsServicesHero from './index';

describe('src/module/sw-settings-services/component/sw-settings-services-hero', () => {
    it('passes down docs and feedback link', async () => {
        const hero = await mount(SwSettingsServicesHero, {
            props: {
                documentationLink: 'https://docs.shopwell.cn/en/shopware-6-en/shopware-services',
                feedbackLink: 'https://feedback.shopwell.cn/forums/961085/suggestions/49977843',
            },
        });

        expect(hero.get('.mt-button--secondary').attributes('href')).toBe(
            'https://feedback.shopwell.cn/forums/961085/suggestions/49977843',
        );
        expect(hero.get('.mt-link--primary.mt-link--external').attributes('href')).toBe(
            'https://docs.shopwell.cn/en/shopware-6-en/shopware-services',
        );
    });
});
