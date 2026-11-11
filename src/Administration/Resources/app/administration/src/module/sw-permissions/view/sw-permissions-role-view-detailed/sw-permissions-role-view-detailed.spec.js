/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-permissions-role-view-detailed', {
            sync: true,
        }),
        {
            props: {
                role: {},
                detailedPrivileges: [],
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-permissions-detailed-grid': true,
                    'sw-permissions-detailed-additional': true,
                },
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                },
            },
        },
    );
}

describe('module/sw-permissions/view/sw-permissions-role-view-detailed', () => {
    it('should disable the detailed permission grid when no aclPrivileges exists', async () => {
        const wrapper = await createWrapper();

        const detailedPermissionGrid = wrapper.find('sw-permissions-detailed-grid-stub');

        expect(detailedPermissionGrid.attributes().disabled).toBe('true');
    });

    it('should enable the detailed permission grid when edit aclPrivileges exists', async () => {
        const wrapper = await createWrapper(['permissions.editor']);

        const detailedPermissionGrid = wrapper.find('sw-permissions-detailed-grid-stub');

        expect(detailedPermissionGrid.attributes().disabled).toBeUndefined();
    });

    it('should show an alert which contains the help text', async () => {
        const wrapper = await createWrapper();

        const alert = wrapper.find('[role="banner"]');
        expect(alert.text()).toBe('sw-permissions.roles.view.detailed.alertText');
    });
});
