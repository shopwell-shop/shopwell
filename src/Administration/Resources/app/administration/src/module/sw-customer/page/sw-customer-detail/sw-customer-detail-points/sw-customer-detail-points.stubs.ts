/**
 * @sw-package checkout
 */

function cardStubs() {
    return {
        'mt-card': {
            inheritAttrs: false,
            props: ['title', 'isLoading'],
            template: `<section class="mt-card-stub" v-bind="$attrs" :data-loading="isLoading">
                <h2 class="mt-card-stub__title">{{ title }}</h2>
                <slot name="headerRight"></slot>
                <slot></slot>
            </section>`,
        },
        'mt-badge': {
            inheritAttrs: false,
            props: ['type', 'size'],
            template: '<span class="mt-badge-stub" v-bind="$attrs" :data-type="type"><slot></slot></span>',
        },
        'mt-empty-state': {
            inheritAttrs: false,
            props: ['headline', 'icon'],
            template: '<div class="mt-empty-state-stub" v-bind="$attrs">{{ headline }}</div>',
        },
    };
}

function formStubs() {
    return {
        'mt-button': {
            inheritAttrs: false,
            props: ['disabled', 'isLoading'],
            emits: ['click'],
            template: `<button
                class="mt-button-stub"
                v-bind="$attrs"
                :disabled="disabled || undefined"
                :data-loading="isLoading"
                @click="$emit('click', $event)"
            ><slot></slot></button>`,
        },
        'mt-number-field': {
            inheritAttrs: false,
            props: ['modelValue'],
            emits: ['update:model-value'],
            template: `<input
                class="mt-number-field-stub"
                v-bind="$attrs"
                type="number"
                :value="modelValue"
                @input="$emit('update:model-value', Number($event.target.value))"
            />`,
        },
        'mt-textarea': {
            inheritAttrs: false,
            props: ['modelValue'],
            emits: ['update:model-value'],
            template: `<textarea
                class="mt-textarea-stub"
                v-bind="$attrs"
                :value="modelValue"
                @input="$emit('update:model-value', $event.target.value)"
            ></textarea>`,
        },
        'mt-progress-bar': {
            inheritAttrs: false,
            props: ['modelValue', 'maxValue'],
            template:
                '<div class="mt-progress-bar-stub" v-bind="$attrs" :data-value="modelValue" :data-max="maxValue"></div>',
        },
    };
}

function selectStub() {
    return {
        'mt-select': {
            inheritAttrs: false,
            props: ['modelValue', 'options'],
            emits: ['update:model-value'],
            template: `<select
                class="mt-select-stub"
                v-bind="$attrs"
                :data-selected="String(modelValue)"
                @change="$emit('update:model-value', $event.target.value === '' ? null : $event.target.value)"
            >
                <option
                    v-for="option in options"
                    :key="String(option.value)"
                    :value="option.value === null ? '' : option.value"
                >{{ option.label }}</option>
            </select>`,
        },
    };
}

function dataStubs() {
    return {
        'sw-data-grid': {
            inheritAttrs: false,
            props: ['dataSource', 'columns'],
            template: `<div class="sw-data-grid-stub" v-bind="$attrs">
                <div
                    v-for="item in dataSource"
                    :key="item.id"
                    class="sw-data-grid-stub__row"
                >
                    <div
                        v-for="column in columns"
                        :key="column.property"
                        :class="'sw-data-grid-stub__cell sw-data-grid-stub__cell-' + column.property"
                    >
                        <slot :name="'column-' + column.property" :item="item">{{ item[column.property] }}</slot>
                    </div>
                </div>
            </div>`,
        },
        'sw-pagination': {
            inheritAttrs: false,
            props: ['page', 'limit', 'total'],
            emits: ['page-change'],
            template: `<button
                class="sw-pagination-stub"
                v-bind="$attrs"
                :data-page="page"
                @click="$emit('page-change', { page: page + 1, limit: limit })"
            ></button>`,
        },
    };
}

function layoutStubs() {
    return {
        'sw-container': {
            inheritAttrs: false,
            template: '<div class="sw-container-stub" v-bind="$attrs"><slot></slot></div>',
        },
        'sw-modal': {
            inheritAttrs: false,
            props: ['title'],
            emits: ['modal-close'],
            template: `<div class="sw-modal-stub" v-bind="$attrs">
                <h3 class="sw-modal-stub__title">{{ title }}</h3>
                <div class="sw-modal-stub__body"><slot></slot></div>
                <footer class="sw-modal-stub__footer"><slot name="modal-footer"></slot></footer>
            </div>`,
        },
    };
}

/**
 * Flattens the Meteor and Shopwell containers of the customer points tab into plain elements that
 * render their slots, so a spec can read what the tab shows instead of reaching into the component.
 *
 * Kept next to the spec rather than inside it, because the stub markup would bury the assertions.
 *
 * @private
 */
export function stubs() {
    return {
        ...cardStubs(),
        ...formStubs(),
        ...selectStub(),
        ...dataStubs(),
        ...layoutStubs(),
    };
}
