/**
 * @sw-package framework
 *
 * Initializes the Shopwell global object and core registries for the test environment.
 * Previously provided by @shopware-ag/jest-preset-sw6-admin.
 */
const { join, resolve } = require('path');

const srcPath = global.adminPath;
if (!srcPath) {
    throw new Error('"globals.adminPath" is not defined. A file path to a Shopwell 6 administration is required');
}

global.window._features_ = {};
global.window.startApplication = global.window.startApplication || (() => {});

const Shopwell = require(resolve(join(srcPath, 'src/core/shopware.ts'))).ShopwellInstance;

const envBefore = process.env.NODE_ENV;

// vue.cjs.js loads different files based on NODE_ENV
process.env.NODE_ENV = 'production';

const { createApp } = require(resolve(join(srcPath, 'node_modules/vue/dist/vue.cjs.js')));
const app = createApp();
app.use(Shopwell.Store._rootState);

process.env.NODE_ENV = envBefore;

module.exports = (() => {
    global.Shopwell = Shopwell;
    require(resolve(join(srcPath, 'src/app/mixin/index'))).default();
    require(resolve(join(srcPath, 'src/app/directive/index'))).default();
    require(resolve(join(srcPath, 'src/app/filter/index'))).default();
    require(resolve(join(srcPath, 'src/app/init-pre/state.init'))).default();
    require(resolve(join(srcPath, 'src/app/init/component-helper.init'))).default();
})();
