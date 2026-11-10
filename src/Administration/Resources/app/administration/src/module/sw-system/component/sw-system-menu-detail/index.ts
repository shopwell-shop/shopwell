/**
 * @sw-package fundamentals@framework
 */
import type { PropType } from 'vue';
import type { TreeSelectOption } from 'src/app/component/form/sw-tree-select/tree-options';
import type Repository from 'src/core/data/repository.data';
import type Criteria from 'src/core/data/criteria.data';
import type { MenuDraft } from '../../page/sw-system-menu-list/menu-draft';
import template from './sw-system-menu-detail.html.twig';

type Option = { value: string; label: string };
const options = () => ({ type: Array as PropType<Option[]>, required: true as const });

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    emits: [
        'update-draft',
        'type-change',
        'parent-change',
    ],
    props: {
        draft: { type: Object as PropType<MenuDraft>, required: true },
        disabled: { type: Boolean, default: false },
        locked: { type: Boolean, default: false },
        languageLabel: { type: String, required: true },
        provenance: { type: String, default: '' },
        targetPrivilege: { type: String, default: '' },
        typeOptions: options(),
        parentOptions: { type: Array as PropType<TreeSelectOption[]>, required: true },
        routeOptions: { type: Array as PropType<TreeSelectOption[]>, required: true },
        privilegeOptions: options(),
        aclRoleRepository: { type: Object as PropType<Repository<'acl_role'>>, required: true },
        roleCriteria: { type: Object as PropType<Criteria>, required: true },
        fieldErrors: { type: Object as PropType<Record<string, { code: string; detail: string }>>, required: true },
    },
    computed: {
        visibilityOptions() {
            return ['all', 'roles'].map((value) => ({ value, label: this.$t(`sw-system.menu.visibility.${value}`) }));
        },
    },
    methods: {
        updateField(field: keyof MenuDraft, value: MenuDraft[keyof MenuDraft]) {
            if (!this.disabled) this.$emit('update-draft', { [field]: value });
        },
    },
});
