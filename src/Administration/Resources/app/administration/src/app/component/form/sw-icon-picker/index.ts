/** @sw-package framework */
import template from './sw-icon-picker.html.twig';
import './sw-icon-picker.scss';

declare const __METEOR_ICON_NAMES__: string[];
const PAGE_SIZE = 96;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    emits: ['update:model-value'],
    props: {
        modelValue: { type: String, default: '' },
        label: { type: String, required: true },
        disabled: { type: Boolean, default: false },
    },
    data() {
        return { isOpen: false, term: '', style: 'all', limit: PAGE_SIZE, fieldId: Shopwell.Utils.createId() };
    },
    computed: {
        icons(): string[] {
            return typeof __METEOR_ICON_NAMES__ === 'undefined' ? [] : __METEOR_ICON_NAMES__;
        },
        styleOptions() {
            return ['all', 'regular', 'solid'].map((value) => ({ value, label: this.$t(`global.sw-icon-picker.${value}`) }));
        },
        filteredIcons() {
            return this.icons.filter(
                (name) =>
                    (this.style === 'all' || name.startsWith(`${this.style}-`)) &&
                    name.toLocaleLowerCase().includes(this.term.trim().toLocaleLowerCase()),
            );
        },
        visibleIcons() {
            return this.filteredIcons.slice(0, this.limit);
        },
    },
    watch: {
        term() {
            this.limit = PAGE_SIZE;
        },
        style() {
            this.limit = PAGE_SIZE;
        },
        disabled(value: boolean) {
            if (value) this.isOpen = false;
        },
    },
    methods: {
        open() {
            if (!this.disabled) {
                this.term = '';
                this.style = 'all';
                this.limit = PAGE_SIZE;
                this.isOpen = true;
            }
        },
        choose(name: string) {
            if (!this.disabled) {
                this.$emit('update:model-value', name);
                this.isOpen = false;
            }
        },
    },
});
