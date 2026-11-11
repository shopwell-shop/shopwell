/**
 * @sw-package fundamentals@framework
 */
import { mount, flushPromises } from '@vue/test-utils';

const mockRole = {
    id: 'test-role-id',
    name: 'Test Role',
    description: '',
    isNew: jest.fn(() => true),
};

const repositoryFactory = {
    create: jest.fn(() => ({
        search: jest.fn(() => Promise.resolve([])),
    })),
};

async function createWrapper(privileges = [], options = {}) {
    return mount(
        await wrapTestComponent('sw-permissions-role-view-general', {
            sync: true,
        }),
        {
            props: {
                role: options.role ?? mockRole,
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'mt-banner': true,
                    'mt-textarea': true,
                    'sw-text-field': true,
                    'mt-number-field': true,
                    'sw-permissions-grid': true,
                    'sw-permissions-additional': true,
                    'sw-permissions-role-mcp-reference-modal': true,
                },
                provide: {
                    repositoryFactory: options.repositoryFactory ?? repositoryFactory,
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

describe('module/sw-permissions/view/sw-permissions-role-view-general', () => {
    it('should disable the button and fields when no aclPrivileges exists', async () => {
        const wrapper = await createWrapper();

        const fieldRoleName = wrapper.find('input[aria-label="sw-permissions.roles.detail.labelName"]');
        const fieldRoleDescription = wrapper.find('mt-textarea-stub[label="sw-permissions.roles.detail.labelDescription"]');

        expect(fieldRoleName.attributes().disabled).toBeDefined();
        expect(fieldRoleDescription.attributes().disabled).toBe('true');
    });

    describe('mcp hint visibility', () => {
        const savedRole = { ...mockRole, isNew: jest.fn(() => false) };

        function repositoryFactoryReturning(integrations) {
            return {
                create: jest.fn(() => ({
                    search: jest.fn(() => Promise.resolve(integrations)),
                })),
            };
        }

        it('shows the hint when an integration restricts only resources (tools=null, resources=[])', async () => {
            const wrapper = await createWrapper(['permissions.editor'], {
                role: savedRole,
                repositoryFactory: repositoryFactoryReturning([
                    { id: 'int-1', mcpAllowlist: { tools: null, resources: [], prompts: null } },
                ]),
            });
            await flushPromises();

            expect(wrapper.vm.shouldShowMcpHint).toBe(true);
        });

        it('shows the hint when an integration restricts only prompts (tools=null, prompts=[])', async () => {
            const wrapper = await createWrapper(['permissions.editor'], {
                role: savedRole,
                repositoryFactory: repositoryFactoryReturning([
                    { id: 'int-1', mcpAllowlist: { tools: null, resources: null, prompts: [] } },
                ]),
            });
            await flushPromises();

            expect(wrapper.vm.shouldShowMcpHint).toBe(true);
        });

        it('shows the hint when an integration restricts tools (legacy case)', async () => {
            const wrapper = await createWrapper(['permissions.editor'], {
                role: savedRole,
                repositoryFactory: repositoryFactoryReturning([
                    { id: 'int-1', mcpAllowlist: { tools: ['x'], resources: null, prompts: null } },
                ]),
            });
            await flushPromises();

            expect(wrapper.vm.shouldShowMcpHint).toBe(true);
        });

        it('hides the hint when no integration has any restriction', async () => {
            const wrapper = await createWrapper(['permissions.editor'], {
                role: savedRole,
                repositoryFactory: repositoryFactoryReturning([
                    { id: 'int-1', mcpAllowlist: { tools: null, resources: null, prompts: null } },
                ]),
            });
            await flushPromises();

            expect(wrapper.vm.shouldShowMcpHint).toBe(false);
        });
    });

    it('should enable the button and fields when edit aclPrivileges exists', async () => {
        const wrapper = await createWrapper(['permissions.editor']);

        const fieldRoleName = wrapper.find('input[aria-label="sw-permissions.roles.detail.labelName"]');
        const fieldRoleDescription = wrapper.find('mt-textarea-stub[label="sw-permissions.roles.detail.labelDescription"]');

        expect(fieldRoleName.attributes().disabled).toBeUndefined();
        expect(fieldRoleDescription.attributes().disabled).toBeUndefined();
    });
});
