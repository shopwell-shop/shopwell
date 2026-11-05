/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type CriteriaType from 'src/core/data/criteria.data';

type Profile = {
    id: string;
    paymentAppId: string;
    technicalName: string;
    active: boolean;
    allowedMethods: string[] | null;
    routingPolicy: string;
    isNew: () => boolean;
};
const initialProfile: Profile = {
    id: 'profile-1',
    paymentAppId: 'app-1',
    technicalName: 'h5',
    active: true,
    allowedMethods: ['alipay'],
    routingPolicy: 'priority',
    isNew: () => false,
};
const wrappers: ReturnType<typeof mount>[] = [];

async function createWrapper({ editable = true, profiles = [initialProfile], fails = false } = {}) {
    let saved = { ...initialProfile };
    const repository = {
        search: jest.fn<Promise<Profile[]>, [CriteriaType]>(() =>
            Promise.resolve(profiles.map((profile) => ({ ...profile }))),
        ),
        create: jest.fn(() => ({ id: 'new-profile', isNew: () => true })),
        save: jest.fn((profile: Profile) => {
            saved = { ...profile, isNew: () => false };
            return fails ? Promise.reject(new Error('save failed')) : Promise.resolve();
        }),
        get: jest.fn(() => Promise.resolve({ ...saved })),
        delete: jest.fn(() => Promise.resolve()),
    };
    const wrapper = mount(await wrapTestComponent('sw-payment-cashier-profiles', { sync: true }), {
        props: {
            appId: 'app-1',
            methodOptions: [
                { value: 'alipay', label: 'Alipay' },
                { value: 'wechat', label: 'WeChat Pay' },
            ],
        },
        global: {
            provide: { repositoryFactory: { create: () => repository }, acl: { can: () => editable } },
            stubs: {
                'mt-card': { template: '<section><slot name="headerRight"></slot><slot></slot></section>' },
                'mt-button': { template: '<button><slot></slot></button>' },
                'sw-skeleton': true,
                'mt-text-field': {
                    props: ['modelValue', 'disabled'],
                    emits: ['update:modelValue'],
                    template: `<input :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)">`,
                },
                'mt-switch': {
                    props: ['modelValue', 'disabled'],
                    emits: ['update:modelValue'],
                    template: `<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.checked)">`,
                },
                'sw-multi-select': {
                    props: ['modelValue', 'disabled', 'options'],
                    emits: ['update:modelValue'],
                    template: `<select multiple :disabled="disabled" :value="modelValue" @change="$emit('update:modelValue', Array.from($event.target.selectedOptions).map(option => option.value))"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
                },
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();
    return { wrapper, repository };
}

const profileRows = (wrapper: ReturnType<typeof mount>) => wrapper.findAll('.sw-payment-cashier-profiles__profile');

describe('business cashier profiles', () => {
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));

    it('loads only profiles belonging to the selected app', async () => {
        const { wrapper, repository } = await createWrapper();
        expect(repository.search.mock.calls[0][0].filters).toContainEqual({
            type: 'equals',
            field: 'paymentAppId',
            value: 'app-1',
        });
        expect(wrapper.find('.sw-payment-cashier-profiles__name').element).toHaveProperty('value', 'h5');
        expect(wrapper.find('details').exists()).toBe(false);
    });

    it('creates and saves an arbitrary profile identifier with a logical method allowlist', async () => {
        const { wrapper, repository } = await createWrapper();
        await wrapper.find('.sw-payment-cashier-profiles__add').trigger('click');
        const added = profileRows(wrapper)[1];
        await added.find('.sw-payment-cashier-profiles__name').setValue('  membership_web  ');
        await added.find('select').setValue(['wechat']);
        await added.find('.sw-payment-cashier-profiles__save').trigger('click');
        await flushPromises();
        expect(repository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                paymentAppId: 'app-1',
                technicalName: 'membership_web',
                allowedMethods: ['wechat'],
                routingPolicy: 'priority',
                active: true,
            }),
            Shopwell.Context.api,
        );
        expect(repository.get).toHaveBeenCalledWith('new-profile', Shopwell.Context.api);
    });

    it('saves the routing policy and activation state without changing the profile id', async () => {
        const { wrapper, repository } = await createWrapper();
        await wrapper.find('.sw-payment-cashier-profiles__policy').setValue('weighted');
        await wrapper.find('input[type="checkbox"]').setValue(false);
        await wrapper.find('.sw-payment-cashier-profiles__save').trigger('click');
        await flushPromises();
        expect(repository.save).toHaveBeenCalledWith(
            expect.objectContaining({ id: 'profile-1', routingPolicy: 'weighted', active: false }),
            Shopwell.Context.api,
        );
    });

    it('treats an empty method allowlist as unrestricted', async () => {
        const { wrapper, repository } = await createWrapper({ profiles: [{ ...initialProfile, allowedMethods: null }] });
        await wrapper.find('.sw-payment-cashier-profiles__save').trigger('click');
        await flushPromises();
        expect(repository.save).toHaveBeenCalledWith(expect.objectContaining({ allowedMethods: [] }), Shopwell.Context.api);
    });

    it('does not save a profile with an empty identifier', async () => {
        const { wrapper, repository } = await createWrapper({ profiles: [] });
        await wrapper.find('.sw-payment-cashier-profiles__add').trigger('click');
        expect(wrapper.find('.sw-payment-cashier-profiles__save').attributes('disabled')).toBeDefined();
        expect(repository.save).not.toHaveBeenCalled();
    });

    it('keeps an edited profile available for retry after save failure', async () => {
        const { wrapper, repository } = await createWrapper({ fails: true });
        await wrapper.find('.sw-payment-cashier-profiles__name').setValue('retry_cashier');
        await wrapper.find('.sw-payment-cashier-profiles__save').trigger('click');
        await flushPromises();
        expect(repository.get).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-payment-cashier-profiles__name').element).toHaveProperty('value', 'retry_cashier');
        expect(wrapper.find('.sw-payment-cashier-profiles__save').attributes('disabled')).toBeUndefined();
    });

    it('deletes a stored profile through the repository', async () => {
        const { wrapper, repository } = await createWrapper();
        await wrapper.find('.sw-payment-cashier-profiles__delete').trigger('click');
        await flushPromises();
        expect(repository.delete).toHaveBeenCalledWith('profile-1', Shopwell.Context.api);
        expect(profileRows(wrapper)).toHaveLength(0);
    });

    it('removes an unsaved profile without sending a delete request', async () => {
        const { wrapper, repository } = await createWrapper({ profiles: [] });
        await wrapper.find('.sw-payment-cashier-profiles__add').trigger('click');
        await wrapper.find('.sw-payment-cashier-profiles__delete').trigger('click');
        await flushPromises();
        expect(repository.delete).not.toHaveBeenCalled();
        expect(profileRows(wrapper)).toHaveLength(0);
    });

    it('allows viewers to read profiles while disabling all edits', async () => {
        const { wrapper, repository } = await createWrapper({ editable: false });
        expect(wrapper.find('.sw-payment-cashier-profiles__add').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-payment-cashier-profiles__name').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-payment-cashier-profiles__delete').attributes('disabled')).toBeDefined();
        expect(repository.save).not.toHaveBeenCalled();
    });
});
