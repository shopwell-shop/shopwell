/**
 * @sw-package framework
 */

const { warn } = Shopwell.Utils.debug;
const Sanitizer = Shopwell.Helper.SanitizerHelper;

let pluginInstalled = false;

/**
 * @private
 */
export default {
    install(app) {
        if (pluginInstalled) {
            warn('Sanitize Plugin', 'This plugin is already installed');
            return false;
        }

        app.config.globalProperties.$sanitizer = Sanitizer;
        app.config.globalProperties.$sanitize = Sanitizer.sanitize;

        pluginInstalled = true;

        return true;
    },
};
