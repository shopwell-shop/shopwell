/** @sw-package framework */
import type { PropType } from 'vue';
import template from './sw-tree-select.html.twig';
import { selectedTreePath, visibleTreeOptions } from './tree-options';
import type { TreeSelectOption, TreeSelectRow } from './tree-options';
import './sw-tree-select.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    emits: ['update:model-value'],
    props: {
        modelValue: { type: String, default: '' },
        options: { type: Array as PropType<TreeSelectOption[]>, required: true },
        label: { type: String, required: true },
        placeholder: { type: String, default: '' },
        disabled: { type: Boolean, default: false },
        required: { type: Boolean, default: false },
        helpText: { type: String, default: '' },
    },
    data() {
        return { isOpen: false, term: '', expanded: [] as string[], focusedIndex: 0, fieldId: Shopwell.Utils.createId() };
    },
    computed: {
        selectedPath() {
            return selectedTreePath(this.options, this.modelValue);
        },
        rows() {
            return visibleTreeOptions(this.options, this.expanded, this.term);
        },
    },
    watch: {
        disabled(value: boolean) {
            if (value) this.isOpen = false;
        },
        term() {
            this.focusedIndex = 0;
        },
    },
    methods: {
        open() {
            if (this.disabled) return;
            this.isOpen = !this.isOpen;
            if (!this.isOpen) return;
            this.term = '';
            // Reveal the selected path without changing the model.
            const reveal = (options: TreeSelectOption[]): boolean =>
                options.some((option) => {
                    if (option.value === this.modelValue) return true;
                    if (!reveal(option.children ?? [])) return false;
                    if (!this.expanded.includes(option.value)) this.expanded.push(option.value);
                    return true;
                });
            reveal(this.options);
            this.focusedIndex = Math.max(
                0,
                this.rows.findIndex((row) => row.value === this.modelValue),
            );
            void this.$nextTick(() => (this.$refs.popup as HTMLElement)?.querySelector('input')?.focus());
        },
        toggle(row: TreeSelectRow) {
            this.expanded = this.expanded.includes(row.value)
                ? this.expanded.filter((id) => id !== row.value)
                : [...this.expanded, row.value];
        },
        choose(row: TreeSelectRow) {
            if (this.disabled) return;
            if (row.selectable === false) {
                this.toggle(row);
                return;
            }
            this.$emit('update:model-value', row.value);
            this.close();
        },
        close() {
            this.isOpen = false;
            void this.$nextTick(() => (this.$refs.trigger as { $el?: HTMLElement })?.$el?.focus());
        },
        onHorizontalKey(key: string, row: TreeSelectRow) {
            if (key === 'ArrowRight' && row.children?.length && !this.expanded.includes(row.value)) this.toggle(row);
            if (key === 'ArrowLeft' && this.expanded.includes(row.value)) this.toggle(row);
        },
        onSearchKeydown(event: KeyboardEvent) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                (this.$refs.tree as HTMLElement)?.focus();
                return;
            }
            if (event.key === 'Escape' || event.key === 'Tab') this.onKeydown(event);
        },
        onKeydown(event: KeyboardEvent) {
            const row = this.rows[this.focusedIndex];
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                this.close();
                return;
            }
            if (event.key === 'Tab') {
                this.isOpen = false;
                return;
            }
            if (!row) return;
            switch (event.key) {
                case 'ArrowDown':
                    this.focusedIndex = Math.min(this.rows.length - 1, this.focusedIndex + 1);
                    break;
                case 'ArrowUp':
                    this.focusedIndex = Math.max(0, this.focusedIndex - 1);
                    break;
                case 'Home':
                    this.focusedIndex = 0;
                    break;
                case 'End':
                    this.focusedIndex = this.rows.length - 1;
                    break;
                case 'ArrowRight':
                case 'ArrowLeft':
                    this.onHorizontalKey(event.key, row);
                    break;
                case 'Enter':
                    this.choose(row);
                    break;
                default:
                    return;
            }
            event.preventDefault();
            void this.$nextTick(() =>
                (this.$refs.popup as HTMLElement)
                    ?.querySelector('[data-focused="true"]')
                    ?.scrollIntoView?.({ block: 'nearest' }),
            );
        },
    },
});
