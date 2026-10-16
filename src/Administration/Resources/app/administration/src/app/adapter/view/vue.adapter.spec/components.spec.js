/**
 * @sw-package framework
 */

import { shallowMount } from '@vue/test-utils';
import VueAdapter from 'src/app/adapter/view/vue.adapter';
import ViewAdapter from 'src/core/adapter/view.adapter';
import { h } from 'vue';
import { createApplication, prepareVueAdapterEnvironment, resetVueAdapterTestState } from './vue-adapter-test-setup';

describe('ASYNC app/adapter/view/vue.adapter.js - components', () => {
    let vueAdapter;

    beforeEach(() => {
        prepareVueAdapterEnvironment();
        vueAdapter = new VueAdapter(createApplication());
    });

    afterEach(() => {
        resetVueAdapterTestState();
    });

    it('should be an class', async () => {
        const type = typeof VueAdapter;
        expect(type).toBe('function');
    });

    it('should extends the view adapter', async () => {
        const isInstanceOfViewAdapter = VueAdapter.prototype instanceof ViewAdapter;
        expect(isInstanceOfViewAdapter).toBeTruthy();
    });

    it('should resolve mixins by explicit Mixin get by name call', async () => {
        Shopwell.Mixin.register('foo1', {
            methods: {
                fooBar() {
                    return this.title;
                },
            },
        });

        Shopwell.Component.register('test-component1', {
            template: '<div></div>',
            name: 'test-component1',
            data() {
                return {
                    title: 'testComponent',
                };
            },
            mixins: [Shopwell.Mixin.getByName('foo1')],
            methods: {
                bar() {
                    return 'bar';
                },
            },
        });

        Shopwell.Component.markComponentAsSync('test-component1');
        const buildComp = await vueAdapter.createComponent('test-component1');

        const wrapper = shallowMount(await buildComp);

        expect(wrapper.vm.fooBar).toBeDefined();
        expect(wrapper.vm.bar).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponent');
        expect(wrapper.vm.bar()).toBe('bar');
    });

    it('should resolve mixins by explicit Mixin get by name call with override', async () => {
        Shopwell.Mixin.register('foo2', {
            methods: {
                fooBar() {
                    return this.title;
                },
            },
        });

        Shopwell.Component.register('test-component2', {
            template: '<div></div>',
            name: 'test-component2',
            data() {
                return {
                    title: 'testComponent',
                };
            },
            mixins: [Shopwell.Mixin.getByName('foo2')],
            methods: {
                bar() {
                    return 'bar';
                },
            },
        });

        Shopwell.Component.override('test-component2', {
            data() {
                return {
                    title: 'testComponentOverride',
                };
            },
            methods: {
                buz() {
                    return 'buz';
                },
            },
        });

        Shopwell.Component.markComponentAsSync('test-component2');
        const buildComp = await vueAdapter.createComponent('test-component2');
        const wrapper = shallowMount(await buildComp);

        expect(wrapper.vm.fooBar).toBeDefined();
        expect(wrapper.vm.bar).toBeDefined();
        expect(wrapper.vm.buz).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponentOverride');
        expect(wrapper.vm.bar()).toBe('bar');
        expect(wrapper.vm.buz()).toBe('buz');
    });

    it('should resolve mixins by string', async () => {
        Shopwell.Mixin.register('foo3', {
            methods: {
                fooBar() {
                    return this.title;
                },
            },
        });

        Shopwell.Component.register('test-component3', {
            template: '<div></div>',
            name: 'test-component3',
            data() {
                return {
                    title: 'testComponent3',
                };
            },
            mixins: ['foo3'],
            methods: {
                bar() {},
            },
        });

        Shopwell.Component.markComponentAsSync('test-component3');
        const buildComp = await vueAdapter.createComponent('test-component3');
        const wrapper = shallowMount(await buildComp);

        expect(wrapper.vm.fooBar).toBeDefined();
        expect(wrapper.vm.bar).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponent3');
    });

    it('should resolve mixins by string with override', async () => {
        Shopwell.Mixin.register('foo4', {
            methods: {
                fooBar() {
                    return this.title;
                },
            },
        });

        Shopwell.Component.register('test-component4', {
            template: '<div></div>',
            name: 'test-component4',
            data() {
                return {
                    title: 'testComponent4',
                };
            },
            mixins: ['foo4'],
            methods: {
                bar() {},
            },
        });

        Shopwell.Component.override('test-component4', {
            data() {
                return {
                    title: 'testComponentOverride4',
                };
            },
            methods: {
                buz() {},
            },
        });

        Shopwell.Component.markComponentAsSync('test-component4');
        const buildComp = await vueAdapter.createComponent('test-component4');
        const wrapper = shallowMount(await buildComp);

        expect(wrapper.vm.fooBar).toBeDefined();
        expect(wrapper.vm.bar).toBeDefined();
        expect(wrapper.vm.buz).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponentOverride4');
    });

    it('should resolve mixins for component in combination with overrides', async () => {
        Shopwell.Mixin.register('foo-with-data', {
            data() {
                return {
                    sortBy: null,
                };
            },
            methods: {
                fooBar() {
                    return this.sortBy;
                },
            },
        });

        Shopwell.Component.register('test-component-foobar-with-mixin', {
            template: '<div></div>',
            name: 'test-component',
            data() {
                return {
                    sortBy: 'date',
                };
            },
            mixins: ['foo-with-data'],
            methods: {
                bar() {},
                fooBar() {
                    return this.sortBy;
                },
            },
        });

        Shopwell.Component.markComponentAsSync('test-component-foobar-with-mixin');
        const buildComp = await vueAdapter.createComponent('test-component-foobar-with-mixin');
        let wrapper = shallowMount(await buildComp);

        expect(wrapper.vm.fooBar).toBeDefined();
        expect(wrapper.vm.bar).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('date');

        // add an override to the component
        Shopwell.Component.override('test-component-foobar-with-mixin', {});

        Shopwell.Component.markComponentAsSync('test-component-foobar-with-mixin');
        const buildOverrideComp = await vueAdapter.createComponent('test-component-foobar-with-mixin');
        wrapper = shallowMount(await buildOverrideComp);

        expect(wrapper.vm.fooBar).toBeDefined();
        expect(wrapper.vm.bar).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('date');
    });

    it('should extend mixins', async () => {
        Shopwell.Mixin.register('swFoo', {
            methods: {
                fooBar() {
                    return this.title;
                },
            },
        });

        Shopwell.Mixin.register('swBar', {
            methods: {
                biz() {
                    return this.title;
                },
                buz() {
                    return 'mixin';
                },
            },
        });

        Shopwell.Component.register('extendable-component', {
            template: '{% block foo %}<div>aaaaa</div>{% endblock %}',
            name: 'extendable-component',
            data() {
                return {
                    title: 'testComponent',
                };
            },
            mixins: ['swFoo'],
            methods: {
                bar() {},
            },
        });

        Shopwell.Component.extend('sw-test-component-extended', 'extendable-component', {
            template: '{% block foo %}<div>bbbbb</div>{% endblock %}',
            mixins: ['swBar'],
            data() {
                return {
                    title: 'testComponentExtended',
                };
            },
            methods: {
                buz() {
                    return 'component';
                },
            },
        });

        Shopwell.Component.markComponentAsSync('sw-test-component-extended');
        const buildComp = await vueAdapter.createComponent('sw-test-component-extended');
        const wrapper = shallowMount(await buildComp);

        expect(wrapper.vm.fooBar).toBeDefined();
        expect(wrapper.vm.bar).toBeDefined();
        expect(wrapper.vm.biz).toBeDefined();
        expect(wrapper.vm.buz).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponentExtended');
        expect(wrapper.vm.buz()).toBe('component');
    });

    it('should allow multi-inheritance with multiple mixins and lifecycle hooks are only executed once', async () => {
        const lifecycleSpy = jest.fn();
        Shopwell.Mixin.register('first-mixin', {
            created() {
                lifecycleSpy();
            },
            methods: {
                foo() {
                    return 'foo';
                },
            },
        });

        Shopwell.Mixin.register('second-mixin', {
            methods: {
                bar() {
                    return 'bar';
                },
            },
        });

        Shopwell.Component.register('base-component', {
            template: '<div class="base-component"></div>',
        });

        Shopwell.Component.override('base-component', {
            mixins: ['first-mixin'],
        });

        Shopwell.Component.override('base-component', {
            mixins: ['second-mixin', 'first-mixin'],
        });

        Shopwell.Component.markComponentAsSync('base-component');
        const buildComp = await vueAdapter.createComponent('base-component');
        const wrapper = shallowMount(await buildComp);

        expect(wrapper.vm.foo).toBeDefined();
        expect(wrapper.vm.bar).toBeDefined();
        expect(wrapper.vm.foo()).toBe('foo');
        expect(wrapper.vm.bar()).toBe('bar');

        expect(lifecycleSpy).toHaveBeenCalledTimes(1);
    });

    it('should build & create a vue.js component', async () => {
        const componentDefinition = {
            name: 'sw-foo',

            render() {
                return h(
                    'div',
                    {
                        class: {
                            'sw-foo': true,
                        },
                    },
                    ['Some text'],
                );
            },
        };

        const component = vueAdapter.buildAndCreateComponent(componentDefinition);
        const mountedComponent = shallowMount(component);
        expect(mountedComponent.vm).toBeTruthy();
    });
});
