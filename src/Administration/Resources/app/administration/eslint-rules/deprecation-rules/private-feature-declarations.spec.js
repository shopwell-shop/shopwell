const { RuleTester } = require('eslint');
const rule = require('./private-feature-declarations');

const ruleTester = new RuleTester({
    languageOptions: {
        parser: require('@typescript-eslint/parser'),
    },
});

ruleTester.run('private-feature-declarations', rule, {
    valid: [
        {
            name: 'Default exports with private declaration',
            code: `
                /**
                 * @private
                 */
                export default function testFunction() {}            
            `,
        },
        {
            name: 'Named export with private declaration',
            code: `
                /**
                 * @private
                 */
                export const testFunction = () => {};
            `,
        },
        {
            name: 'Named exports with private declaration',
            code: `
                function testFunction() {}
                class TestClass {}
            
                /**
                 * @private
                 */
                export { testFunction, TestClass }            
            `,
        },
        {
            name: 'Register calls with private declaration',
            code: `
                /**
                 * @private
                 */
                Shopwell.Component.register('my-component', {})
            `,
        },
        {
            name: 'Nested Register calls with private declaration',
            code: `
                function registerMyComponent() {
                    /**
                     * @private
                     */
                    Shopwell.Module.register('my-module', {
                        component: Shopwell.Component.register('my-component', {})
                    });
                };
            `,
        }
    ],
    invalid: [
        {
            name: 'Default exports without private declaration',
            code: `
                export default function testFunction() {}            
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        },
        {
            name: 'Named export with private declaration',
            code: `
                export const testFunction = () => {};
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        },
        {
            name: 'Named exports with private declaration',
            code: `
                function testFunction() {}
                class TestClass {}
            
                export { testFunction, TestClass }            
            `,
            errors: [{ message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private' }]
        },
        {
            name: 'Register calls with private declaration',
            code: `
                Shopwell.Component.register('my-component', {})
            `,
            errors: [{ message: 'New features need to be private. Old features should be @deprecated tag:v6.X.0 - Will be private',}]
        },
        {
            name: 'Nested Register calls with private declaration',
            code: `
                function registerMyComponent() {
                    Shopwell.Module.register('my-module', {
                        component: Shopwell.Component.register('my-component', {})
                    });
                };
            `,
            errors: [{ message: 'New features need to be private. Old features should be @deprecated tag:v6.X.0 - Will be private',}]
        }
    ]
});

