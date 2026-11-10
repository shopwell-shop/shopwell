/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createRouter, createMemoryHistory, RouterView } from 'vue-router';
import type { Router } from 'vue-router';
import type Criteria from 'src/core/data/criteria.data';
import Entity from 'src/core/data/entity.data';

type UnitRow = {
    id: string;
    parentId: string | null;
    organizationId: string;
    name: string;
    active: boolean;
    translated: { name: string };
};

let router: Router;

let saveSpy: jest.Mock;
let deleteSpy: jest.Mock;
let searchSpy: jest.Mock;
let organizationSearchSpy: jest.Mock;

const units: UnitRow[] = [
    {
        id: 'unit-root',
        parentId: null,
        organizationId: 'org-id',
        name: 'Headquarters',
        active: true,
        translated: { name: 'Headquarters' },
    },
    {
        id: 'unit-child',
        parentId: 'unit-root',
        organizationId: 'org-id',
        name: 'Engineering',
        active: true,
        translated: { name: 'Engineering' },
    },
    {
        id: 'unit-leaf',
        parentId: 'unit-child',
        organizationId: 'org-id',
        name: 'Frontend',
        active: true,
        translated: { name: 'Frontend' },
    },
];

/** @private */
export async function createWrapper(
    privileges: string[] = [],
    startPath = '/organization/org-id',
    nativeTree = false,
    nativeModal = false,
): Promise<VueWrapper> {
    saveSpy = jest.fn(() => Promise.resolve());
    deleteSpy = jest.fn(() => Promise.resolve());
    let storedUnits = [
        ...units.map((unit) => ({ ...unit })),
        {
            ...units[0],
            id: 'asia-unit',
            organizationId: 'other-org',
            name: 'Operations',
            translated: { name: 'Operations' },
        },
    ];
    let storedOrganizations = [
        { id: 'org-id', name: 'Shopwell HQ', active: true },
        { id: 'other-org', name: 'Asia organization', active: true },
    ];
    searchSpy = jest.fn((criteria: Criteria) => {
        const organizationFilter = criteria.filters.find((filter) => 'field' in filter && filter.field === 'organizationId');
        const organizationId = organizationFilter && 'value' in organizationFilter ? organizationFilter.value : null;
        return Promise.resolve(
            storedUnits
                .filter(
                    (unit) =>
                        (!organizationId || unit.organizationId === organizationId) &&
                        (!criteria.term || unit.name.toLowerCase().includes(criteria.term.toLowerCase())),
                )
                .map(
                    (unit) =>
                        new Entity(unit.id, 'organization_unit', {
                            ...unit,
                            translated: { ...unit.translated },
                        } as EntitySchema.Entities['organization_unit']),
                ),
        );
    });
    const organizationRepository = Shopwell.Service('repositoryFactory').create('organization');
    const unitRepository = Shopwell.Service('repositoryFactory').create('organization_unit');
    jest.spyOn(organizationRepository, 'get').mockImplementation((id) =>
        Promise.resolve(
            new Entity(id, 'organization', {
                ...(storedOrganizations.find((organization) => organization.id === id) ?? storedOrganizations[0]),
            } as EntitySchema.Entities['organization']),
        ),
    );
    organizationSearchSpy = jest.fn(() =>
        Promise.resolve(
            Object.assign(
                storedOrganizations.map(
                    (organization) =>
                        new Entity(organization.id, 'organization', {
                            ...organization,
                        } as EntitySchema.Entities['organization']),
                ),
                {
                    total: storedOrganizations.length,
                    aggregations: {
                        unitCounts: {
                            buckets: storedOrganizations.map((organization) => ({
                                key: organization.id,
                                unitCount: {
                                    count: storedUnits.filter((unit) => unit.organizationId === organization.id).length,
                                },
                            })),
                        },
                    },
                },
            ),
        ),
    );
    saveSpy.mockImplementation((entity: EntitySchema.Entity<'organization'> | EntitySchema.Entity<'organization_unit'>) => {
        if (entity.getEntityName() === 'organization_unit') {
            const unit = entity as EntitySchema.Entity<'organization_unit'>;
            storedUnits = [
                ...storedUnits.filter((item) => item.id !== unit.id),
                {
                    id: unit.id,
                    organizationId: unit.organizationId,
                    parentId: unit.parentId ?? null,
                    name: unit.name ?? '',
                    active: unit.active ?? true,
                    translated: { name: unit.name ?? '' },
                },
            ];
        } else {
            storedOrganizations = [
                ...storedOrganizations.filter((item) => item.id !== entity.id),
                {
                    id: entity.id,
                    name: entity.name ?? '',
                    active: entity.active ?? true,
                },
            ];
        }
        return Promise.resolve();
    });
    deleteSpy.mockImplementation((id: string) => {
        storedOrganizations = storedOrganizations.filter((organization) => organization.id !== id);
        const removed = new Set([id]);
        let previousSize = 0;
        while (previousSize !== removed.size) {
            previousSize = removed.size;
            storedUnits.filter((unit) => removed.has(unit.parentId ?? '')).forEach((unit) => removed.add(unit.id));
        }
        storedUnits = storedUnits.filter((unit) => unit.organizationId !== id && !removed.has(unit.id));
        return Promise.resolve();
    });
    jest.spyOn(organizationRepository, 'search').mockImplementation(organizationSearchSpy);
    jest.spyOn(organizationRepository, 'delete').mockImplementation(deleteSpy);
    jest.spyOn(unitRepository, 'search').mockImplementation(searchSpy);
    jest.spyOn(organizationRepository, 'save').mockImplementation(saveSpy);
    jest.spyOn(unitRepository, 'save').mockImplementation(saveSpy);
    jest.spyOn(unitRepository, 'delete').mockImplementation(deleteSpy);
    router = createRouter({
        history: createMemoryHistory(),
        routes: [
            {
                name: 'sw.settings.organization.detail',
                path: '/organization/:id',
                component: await wrapTestComponent('sw-settings-organization-detail', { sync: true }),
                props: (route) => ({
                    organizationId: route.params.id,
                    unitId: route.query.unitId ?? null,
                    parentId: route.query.parentId ?? null,
                }),
                meta: { $module: { icon: 'regular-officebuilding' } },
            },
            {
                name: 'sw.settings.organization.create',
                path: '/create',
                props: { createOrganization: true },
                component: await wrapTestComponent('sw-settings-organization-detail', { sync: true }),
                meta: { $module: { icon: 'regular-officebuilding' } },
            },
            {
                name: 'sw.settings.organization.index',
                path: '/list',
                component: await wrapTestComponent('sw-settings-organization-detail', { sync: true }),
            },
        ],
    });
    await router.push(startPath);
    await router.isReady();
    return shallowMount(RouterView, {
        attachTo: nativeModal ? document.body : undefined,
        global: {
            renderStubDefaultSlot: true,
            plugins: [router],
            mocks: { $router: router },
            provide: {
                shortcutService: { startEventListener: jest.fn(), stopEventListener: jest.fn() },
                repositoryFactory: {
                    create: (entity: string) => (entity === 'organization' ? organizationRepository : unitRepository),
                },
                acl: {
                    can: (key: string) => (key ? privileges.includes(key) : true),
                },
            },
            stubs: {
                'sw-settings-organization-detail__wrapped': false,
                'sw-discard-changes-modal': {
                    name: 'sw-discard-changes-modal',
                    emits: ['keep-editing', 'discard-changes'],
                    template: `<div class="discard-modal"><button class="keep-editing" @click="$emit('keep-editing')">Keep editing</button><button class="discard-changes" @click="$emit('discard-changes')">Discard changes</button></div>`,
                },
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                    </div>
                `,
                },
                'sw-card-view': { template: '<div><slot /></div>' },
                'mt-card': { template: '<section><slot name="grid"/><slot /></section>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-text-field': {
                    props: ['modelValue', 'disabled'],
                    emits: ['update:modelValue'],
                    template: `<input :disabled="disabled" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
                },
                'mt-number-field': {
                    props: ['modelValue', 'disabled'],
                    emits: ['update:modelValue'],
                    template: `<input type="number" :disabled="disabled" :value="modelValue" @input="$emit('update:modelValue', Number($event.target.value))" />`,
                },
                'mt-switch': {
                    props: ['modelValue', 'disabled'],
                    emits: ['update:modelValue'],
                    template: `<input type="checkbox" :disabled="disabled" :checked="modelValue" @change="$emit('update:modelValue', $event.target.checked)" />`,
                },
                'sw-container': {
                    template: `
                            <div class="sw-container">
                                <slot></slot>
                            </div>`,
                },
                'sw-ignore-class': true,
                'sw-tree': await wrapTestComponent('sw-tree', { sync: true }),
                'sw-tree-item': await wrapTestComponent('sw-tree-item', { sync: true }),
                'sw-vnode-renderer': await wrapTestComponent('sw-vnode-renderer', { sync: true }),
                'sw-context-button': { template: '<span><slot /></span>' },
                'sw-settings-organization-unit-tree': nativeTree
                    ? await wrapTestComponent('sw-settings-organization-unit-tree', { sync: true })
                    : {
                          props: ['organizations', 'units', 'selectedUnitId'],
                          emits: [
                              'select-organization',
                              'select-unit',
                              'add-child',
                              'add-root',
                              'delete-unit',
                              'delete-organization',
                          ],
                          template: `
                            <div class="sw-settings-organization-unit-tree">
                                <div v-for="organization in organizations" :key="organization.id">
                                    <button class="tree-organization" @click="$emit('select-organization', organization)">{{ organization.name }}</button>
                                    <button class="tree-add-root" @click="$emit('add-root', organization)">Add unit</button>
                                    <button class="tree-delete-organization" @click="$emit('delete-organization', organization)">Delete organization</button>
                                </div>
                                <template
                                    v-for="unit in units"
                                    :key="unit.id"
                                >
                                    <button
                                        class="tree-unit" :data-organization="unit.organizationId"
                                        @click="$emit('select-unit', unit)"
                                    >{{ unit.name }}</button>
                                    <button
                                        class="tree-add-child"
                                        @click="$emit('add-child', unit)"
                                    >+</button><button class="tree-delete-unit" @click="$emit('delete-unit', unit)">Delete unit</button>
                                </template>
                            </div>`,
                      },
                'sw-search-bar': {
                    props: ['initialSearch', 'initialSearchType', 'typeSearchAlwaysInContainer'],
                    emits: ['search'],
                    template:
                        '<input class="organization-top-search" :value="initialSearch" @input="$emit(\'search\', $event.target.value)" />',
                },
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    emits: ['update:model-value'],
                    template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:model-value', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
                },
                'sw-context-menu-item': true,
                'sw-loader': true,
                'sw-extension-component-section': true,
                'sw-skeleton': true,
                teleport: true,
                'sw-modal': nativeModal
                    ? await wrapTestComponent('sw-modal', { sync: true })
                    : {
                          template: `
                            <div class="sw-modal">
                                <slot></slot>
                                <slot name="modal-footer"></slot>
                            </div>`,
                      },
            },
        },
    });
}

/** @private */
export { router, saveSpy, deleteSpy, searchSpy };
