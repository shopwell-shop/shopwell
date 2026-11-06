/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-system-role-view-detailed', {
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
                    'sw-system-detailed-permissions-grid': true,
                    'sw-system-detailed-additional-permissions': true,
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

describe('module/sw-system/view/sw-system-role-view-detailed', () => {
    it('should disable the detailed permission grid when no aclPrivileges exists', async () => {
        const wrapper = await createWrapper();

        const detailedPermissionGrid = wrapper.find('sw-system-detailed-permissions-grid-stub');

        expect(detailedPermissionGrid.attributes().disabled).toBe('true');
    });

    it('should enable the detailed permission grid when edit aclPrivileges exists', async () => {
        const wrapper = await createWrapper(['users_and_permissions.editor']);

        const detailedPermissionGrid = wrapper.find('sw-system-detailed-permissions-grid-stub');

        expect(detailedPermissionGrid.attributes().disabled).toBeUndefined();
    });

    it('should show an alert which contains the help text', async () => {
        const wrapper = await createWrapper();

        const alert = wrapper.find('[role="banner"]');
        expect(alert.text()).toBe('sw-system.roles.view.detailed.alertText');
    });
});
