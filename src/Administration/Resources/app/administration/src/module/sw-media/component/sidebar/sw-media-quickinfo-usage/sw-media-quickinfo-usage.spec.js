/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const { Module } = Shopwell;
const ModuleFactory = Module;
const register = ModuleFactory.register;

const itemDeleteMock = (options = {}) => {
    return {
        getEntityName: () => {
            return 'media';
        },
        id: '4a12jd3kki9yyy765gkn5hdb',
        fileName: 'demo.jpg',
        avatarUsers: [],
        categories: [],
        productManufacturers: [],
        productMedia: [],
        mailTemplateMedia: [],
        documentBaseConfigs: [],
        paymentMethods: [],
        shippingMethods: [],

        ...options,
    };
};

const createWrapper = async (repositoryFactoryMock) => {
    return mount(await wrapTestComponent('sw-media-quickinfo-usage', { sync: true }), {
        props: { item: itemDeleteMock() },
        global: {
            stubs: {
                'router-link': true,
                'sw-loader': true,
            },
            provide: {
                repositoryFactory: repositoryFactoryMock ?? {
                    create: () => {
                        return {
                            search: () => {
                                return Promise.resolve([]);
                            },
                        };
                    },
                },
            },
        },
    });
};

describe('module/sw-media/components/sw-media-quickinfo-usage', () => {
    let wrapper;
    let moduleMock;
    beforeEach(async () => {
        wrapper = await createWrapper();

        const modules = ModuleFactory.getModuleRegistry();
        modules.clear();

        moduleMock = {
            type: 'core',
            name: 'settings',
            routes: {
                index: {
                    component: 'sw-settings-index',
                    path: 'index',
                    icon: 'default-action-settings',
                },
            },
            manifest: {
                color: '#9AA8B5',
                icon: 'default-action-settings',
            },
        };
    });

    it('should be correct to show media in used information when user select a media', async () => {
        register('sw-product', moduleMock);
        const productMediaMock = {
            id: '98hhh7gh31d2d23dj292hjd7b',
            product: {
                translated: { name: 'name test' },
            },
        };

        await wrapper.setProps({
            item: itemDeleteMock({ productMedia: [productMediaMock] }),
        });
        expect(wrapper.vm.getUsages.some((usage) => usage.name === productMediaMock.product.translated.name)).toBeTruthy();
    });

    it('should be correct show all of media in used information', async () => {
        await wrapper.unmount();

        wrapper = await createWrapper({
            create: (entityName) => {
                return {
                    search: () => {
                        if (entityName === 'product') {
                            return Promise.resolve([{ id: 'a', translated: { name: 'Product Media Test' } }]);
                        }

                        if (entityName === 'category') {
                            return Promise.resolve([{ id: 'b', translated: { name: 'Category Media Test' } }]);
                        }

                        if (entityName === 'landing_page') {
                            return Promise.resolve([{ id: 'c', translated: { name: 'Landing Page Media Test' } }]);
                        }

                        return Promise.resolve([]);
                    },
                };
            },
        });

        register('sw-system', moduleMock);
        const avatarUserMock = { username: 'abc123' };

        register('sw-product', moduleMock);
        const productMediaMock = {
            product: { translated: { name: 'product test' } },
        };

        register('sw-category', moduleMock);
        const categoryMock = { translated: { name: 'category test' } };

        register('sw-manufacturer', moduleMock);
        const manufacturerMock = { translated: { name: 'manufacturer test' } };

        register('sw-mail-template', moduleMock);
        const mailTemplateMediaMock = {
            id: '8u7bb3kn5hx82jd01jk1sdc',
            mailTemplate: {
                id: 'k8j7hh6gc5v66fr3rdd222da',
                translated: { description: 'mail test' },
            },
        };

        register('sw-settings-document', moduleMock);
        const documentBaseConfigMock = { name: 'document test' };

        register('sw-settings-payment', moduleMock);
        const paymentMock = {
            translated: { distinguishableName: 'payment test' },
        };

        register('sw-settings-shipping', moduleMock);
        const shippingMock = { translated: { name: 'shipping test' } };

        await wrapper.setProps({
            item: itemDeleteMock({
                avatarUsers: [avatarUserMock],
                productMedia: [productMediaMock],
                categories: [categoryMock],
                productManufacturers: [manufacturerMock],
                mailTemplateMedia: [mailTemplateMediaMock],
                documentBaseConfigs: [documentBaseConfigMock],
                paymentMethods: [paymentMock],
                shippingMethods: [shippingMock],
            }),
        });

        const usages = wrapper.vm.getUsages;
        expect(usages.some((usage) => usage.name === avatarUserMock.username)).toBeTruthy();
        expect(usages.some((usage) => usage.name === productMediaMock.product.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === categoryMock.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === manufacturerMock.translated.name)).toBeTruthy();
        expect(
            usages.some((usage) => usage.name === mailTemplateMediaMock.mailTemplate.translated.description),
        ).toBeTruthy();
        expect(usages.some((usage) => usage.name === paymentMock.translated.distinguishableName)).toBeTruthy();
        expect(usages.some((usage) => usage.name === shippingMock.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === documentBaseConfigMock.name)).toBeTruthy();
    });

    it('does not mutate the original media association collections', async () => {
        await wrapper.unmount();

        wrapper = await createWrapper({
            create: (entityName) => {
                return {
                    search: () => {
                        if (entityName === 'product') {
                            return Promise.resolve([{ id: 'slot-product-id', translated: { name: 'Slot Product' } }]);
                        }

                        if (entityName === 'category') {
                            return Promise.resolve([{ id: 'slot-category-id', translated: { name: 'Slot Category' } }]);
                        }

                        return Promise.resolve([]);
                    },
                };
            },
        });

        register('sw-product', moduleMock);
        register('sw-category', moduleMock);

        const item = itemDeleteMock();
        const originalProductMedia = item.productMedia;
        const originalCategories = item.categories;

        await wrapper.setProps({ item });

        expect(item.productMedia).toBe(originalProductMedia);
        expect(item.productMedia).toHaveLength(0);
        expect(item.categories).toBe(originalCategories);
        expect(item.categories).toHaveLength(0);

        expect(wrapper.vm.getUsages).toEqual(expect.any(Array));
    });
});
