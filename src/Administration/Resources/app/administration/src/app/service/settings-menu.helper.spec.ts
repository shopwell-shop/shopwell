/**
 * @sw-package framework
 */
import { buildSettingsNavigation } from './settings-menu.helper';

const declared = {
    system: [
        {
            id: 'sw-settings-tax',
            name: 'tax',
            group: 'system',
            to: 'sw.settings.tax.index',
            label: 'Tax',
            privilege: 'tax.viewer',
        },
    ],
    plugins: [
        {
            id: 'extension',
            name: 'app',
            group: 'plugins',
            to: { name: 'sw.extension.sdk.index', params: { id: 'app', back: '' } },
            label: 'Extension',
        },
    ],
};

describe('settings menu navigation', () => {
    it('does not restore unmanaged settings for custom roles', () => {
        expect(buildSettingsNavigation(declared, [], { managedKeys: [], allowUnmanaged: false }, [])).toEqual({});
    });

    it('uses configured groups, labels and ordering while preserving app settings', () => {
        const groups = buildSettingsNavigation(
            declared,
            [
                {
                    id: 'settings',
                    internalKey: 'sw-settings',
                    children: [
                        {
                            id: 'tax',
                            internalKey: 'sw-settings-tax',
                            groupName: 'regional',
                            groupTitle: 'Regional settings',
                            title: 'Taxes',
                        },
                        {
                            id: 'currency',
                            internalKey: 'sw.settings.currency.index',
                            type: 'menu',
                            groupName: 'regional',
                            title: 'Currencies',
                        },
                    ],
                },
            ],
            { managedKeys: ['sw-settings-tax'] },
            [{ id: 'currency-menu', path: 'sw.settings.currency.index', privilege: 'currency.viewer' }],
        );
        expect(Object.keys(groups)).toEqual(['regional', 'plugins']);
        expect(groups.regional.map((item) => item.label)).toEqual([
            { label: 'Taxes', translated: false },
            { label: 'Currencies', translated: false },
        ]);
        expect(groups.regional[0]).toMatchObject({
            groupTitle: 'Regional settings',
            to: 'sw.settings.tax.index',
            privilege: 'tax.viewer',
            position: 0,
        });
        expect(groups.plugins).toEqual(declared.plugins);
    });

    it('does not bring back hidden settings or settings moved out of the settings page', () => {
        expect(buildSettingsNavigation(declared, [], { managedKeys: ['sw-settings-tax'] })).toEqual({
            plugins: declared.plugins,
        });
    });

    it('matches a settings module by its declared route when its navigation id differs', () => {
        const groups = buildSettingsNavigation(
            {
                commerce: [
                    {
                        id: 'sw-settings-delivery-time',
                        name: 'delivery-time',
                        group: 'commerce',
                        to: 'sw.settings.delivery.time.index',
                        label: 'Delivery time',
                    },
                ],
            },
            [
                {
                    id: 'settings',
                    internalKey: 'sw-settings',
                    children: [{ id: 'delivery', internalKey: 'sw-settings-delivery-times', groupName: 'commerce' }],
                },
            ],
            { managedKeys: ['sw-settings-delivery-times'] },
            [{ id: 'sw-settings-delivery-times', path: 'sw.settings.delivery.time.index', label: 'Delivery time' }],
        );
        expect(groups.commerce).toHaveLength(1);
        expect(groups.commerce[0]).toMatchObject({ label: 'Delivery time', to: 'sw.settings.delivery.time.index' });
    });

    it('keeps an aliased settings declaration hidden when its managed entry is absent', () => {
        const groups = buildSettingsNavigation(
            {
                commerce: [
                    {
                        id: 'singular',
                        name: 'delivery-time',
                        group: 'commerce',
                        to: 'delivery.index',
                        label: 'Delivery time',
                    },
                ],
            },
            [],
            { managedKeys: ['plural'] },
            [{ id: 'plural', path: 'delivery.index' }],
        );
        expect(groups).toEqual({});
    });

    it('keeps declarations when the navigation request failed', () => {
        expect(buildSettingsNavigation(declared, [], { managedKeys: [] })).toEqual(declared);
    });

    it('turns custom folders into settings sections and supports external links', () => {
        const groups = buildSettingsNavigation(
            {},
            [
                {
                    id: 'settings',
                    internalKey: 'sw-settings',
                    children: [
                        {
                            id: 'resources',
                            type: 'folder',
                            title: 'Resources',
                            children: [{ id: 'docs', type: 'link', title: 'Docs', linkTarget: 'https://example.com' }],
                        },
                    ],
                },
            ],
            { managedKeys: [] },
        );
        expect(groups.resources[0]).toMatchObject({
            groupTitle: 'Resources',
            href: 'https://example.com',
            label: { label: 'Docs', translated: false },
        });
    });
    it('finds settings below a custom parent and retains folder restrictions', () => {
        const groups = buildSettingsNavigation(
            declared,
            [
                {
                    id: 'system',
                    type: 'folder',
                    children: [
                        {
                            id: 'settings',
                            internalKey: 'sw-settings',
                            children: [
                                {
                                    id: 'restricted',
                                    type: 'folder',
                                    title: 'Finance',
                                    privilege: 'finance.viewer',
                                    children: [
                                        { id: 'tax', internalKey: 'sw-settings-tax', title: 'Taxes' },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ],
            { managedKeys: ['sw-settings-tax'] },
        );
        expect(groups.restricted[0]).toMatchObject({
            to: 'sw.settings.tax.index',
            inheritedMenuPrivileges: ['finance.viewer'],
        });
    });
    it.each(['constructor', '__proto__', 'toString'])('supports the custom group name %s', (groupName) => {
        const groups = buildSettingsNavigation(
            declared,
            [
                {
                    id: 'settings',
                    internalKey: 'sw-settings',
                    children: [
                        { id: 'tax', internalKey: 'sw-settings-tax', groupName },
                    ],
                },
            ],
            { managedKeys: ['sw-settings-tax'] },
        );
        expect(groups[groupName]).toHaveLength(1);
    });

    it('retains restrictions on settings and its ancestors', () => {
        const groups = buildSettingsNavigation(
            declared,
            [
                {
                    id: 'parent',
                    privilege: 'system.viewer',
                    children: [
                        {
                            id: 'settings',
                            internalKey: 'sw-settings',
                            privilege: 'settings.viewer',
                            children: [
                                { id: 'tax', internalKey: 'sw-settings-tax' },
                            ],
                        },
                    ],
                },
            ],
            { managedKeys: ['sw-settings-tax'] },
        );
        expect(groups.general[0].inheritedMenuPrivileges).toEqual(['system.viewer', 'settings.viewer']);
    });
});

describe('menu refinements', () => {
    it('adds undeclared settings to their configured group with its title and restrictions', () => {
        const groups = buildSettingsNavigation(
            {
                customer: [
                    { id: 'customer-groups', name: 'groups', group: 'customer', to: 'customer.groups' },
                    { id: 'membership', name: 'membership', group: () => 'customer', to: 'membership.index' },
                ],
            },
            [
                {
                    id: 'settings',
                    internalKey: 'sw-settings',
                    privilege: 'settings.viewer',
                    children: [
                        {
                            id: 'customer-group-id',
                            type: 'group',
                            groupName: 'customer',
                            title: 'Customer tools',
                            privilege: 'customer.viewer',
                            children: [{ id: 'groups', internalKey: 'customer-groups' }],
                        },
                    ],
                },
            ],
            { managedKeys: ['customer-groups'] },
        );

        expect(Object.keys(groups)).toEqual(['customer-group-id']);
        expect(groups['customer-group-id'].map((item) => item.to)).toEqual(['customer.groups', 'membership.index']);
        expect(groups['customer-group-id'][1]).toMatchObject({
            group: 'customer-group-id',
            groupTitle: 'Customer tools',
            inheritedMenuPrivileges: ['settings.viewer', 'customer.viewer'],
        });
    });

    it('keeps separate configured groups even when their names and titles match', () => {
        const groups = buildSettingsNavigation(
            {},
            [
                {
                    id: 'settings',
                    internalKey: 'sw-settings',
                    children: [
                        {
                            id: 'first',
                            type: 'group',
                            groupName: 'customer',
                            title: 'Customers',
                            children: [{ id: 'one', type: 'link', linkTarget: 'https://example.com/one' }],
                        },
                        {
                            id: 'second',
                            type: 'group',
                            groupName: 'customer',
                            title: 'Customers',
                            children: [{ id: 'two', type: 'link', linkTarget: 'https://example.com/two' }],
                        },
                    ],
                },
            ],
            { managedKeys: [] },
        );
        expect(Object.keys(groups)).toEqual(['first', 'second']);
        expect(groups.first).toHaveLength(1);
        expect(groups.second).toHaveLength(1);
    });

    it('loads independent group nodes as dynamic settings sections', () => {
        const result = buildSettingsNavigation(
            {},
            [
                {
                    id: 'settings',
                    internalKey: 'sw-settings',
                    children: [
                        {
                            id: 'regional',
                            type: 'group',
                            title: 'Regional tools',
                            children: [{ id: 'help', type: 'link', linkTarget: 'https://example.com' }],
                        },
                    ],
                },
            ],
            { managedKeys: [] },
        );
        expect(Object.keys(result)).toEqual(['regional']);
        expect(result.regional[0].groupTitle).toBe('Regional tools');
    });
});
