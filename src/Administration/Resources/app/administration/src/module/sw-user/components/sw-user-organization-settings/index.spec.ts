/** @sw-package fundamentals@framework */
import { mount } from '@vue/test-utils';
import type { OrganizationAssignments } from '../../types/user-organization.types';

async function createWrapper(value: OrganizationAssignments, disabled = false) {
    const wrapper = mount(await wrapTestComponent('sw-user-organization-settings', { sync: true }), {
        props: { value, disabled },
        global: {
            provide: {
                repositoryFactory: {
                    create: (entity: string) => ({
                        search: jest.fn().mockResolvedValue(
                            entity === 'organization_unit'
                                ? [
                                      { id: 'sales', organizationId: 'company', name: 'Sales' },
                                      { id: 'finance', organizationId: 'company', name: 'Finance' },
                                  ]
                                : [{ id: 'manager', name: 'Manager' }],
                        ),
                    }),
                },
            },
            stubs: {
                'mt-card': { props: ['title'], template: '<article><h3>{{ title }}</h3><slot /></article>' },
                'mt-switch': {
                    props: ['modelValue', 'label', 'disabled'],
                    template:
                        '<input type="checkbox" :aria-label="label" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:model-value\', $event.target.checked)">',
                },
                'mt-number-field': true,
                'mt-select': true,
                'sw-entity-multi-id-select': {
                    props: ['label', 'value', 'disabled'],
                    template:
                        '<input :aria-label="label" :value="value.join(\',\')" :disabled="disabled" @change="$emit(\'update:value\', $event.target.value.split(\',\'))">',
                },
                'mt-banner': { template: '<p><slot /></p>' },
                'mt-button': true,
            },
        },
    });
    await flushPromises();
    return wrapper;
}

function draft(): OrganizationAssignments {
    return {
        departments: [
            {
                id: 'sales-membership',
                organizationUnitId: 'sales',
                isPrimary: true,
                isLeader: false,
                leaderPosition: 0,
                supervisorMembershipId: null,
                positionIds: ['manager'],
            },
            {
                id: 'finance-membership',
                organizationUnitId: 'finance',
                isPrimary: false,
                isLeader: false,
                leaderPosition: 0,
                supervisorMembershipId: null,
                positionIds: [],
            },
        ],
        primaryPosition: 'sales:manager',
    };
}

describe('sw-user-organization-settings', () => {
    it('edits a department leader without mutating another department or the supplied draft', async () => {
        const value = draft();
        const wrapper = await createWrapper(value);
        await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);
        const changed = wrapper.emitted('update:value')?.[0]?.[0] as OrganizationAssignments;

        expect(changed.departments[1].isLeader).toBe(true);
        expect(changed.departments[0]).toEqual(value.departments[0]);
        expect(value.departments[1].isLeader).toBe(false);
        expect(wrapper.find('input[aria-label="sw-user.users.user-detail.labelSupervisor"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('scopes a selected position to the edited department', async () => {
        const wrapper = await createWrapper(draft());
        const positions = wrapper.findAll('input[aria-label="sw-user.users.user-detail.labelPositions"]');
        await positions[1].setValue('manager');
        const changed = wrapper.emitted('update:value')?.[0]?.[0] as OrganizationAssignments;

        expect(changed.departments.map((department) => department.positionIds)).toEqual([['manager'], ['manager']]);
        expect(changed.primaryPosition).toBe('sales:manager');
        wrapper.unmount();
    });

    it('keeps reporting controls read-only for viewers', async () => {
        const wrapper = await createWrapper(draft(), true);

        expect(wrapper.findAll('input').every((input) => input.attributes('disabled') !== undefined)).toBe(true);
        expect(wrapper.emitted('update:value')).toBeUndefined();
        wrapper.unmount();
    });
});
