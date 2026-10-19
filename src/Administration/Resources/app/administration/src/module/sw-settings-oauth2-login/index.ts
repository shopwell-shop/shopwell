/**
 * @sw-package framework
 */
import './acl';
import OAuth2ChannelService from './service/oauth2-channel.api.service';

const { Module } = Shopwell;

/**
 * @sw-package framework
 * @private
 */
Shopwell.Component.register('sw-settings-oauth2-login', () => import('./page/sw-settings-oauth2-login'));

/**
 * @sw-package framework
 * @private
 */
Shopwell.Service().register('oauth2ChannelService', () => {
    return new OAuth2ChannelService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service().get('loginService'),
    );
});

/**
 * @private
 */
Module.register('sw-settings-oauth2-login', {
    type: 'core',
    name: 'settings-oauth2-login',
    title: 'sw-settings-oauth2-login.general.mainMenuItemGeneral',
    description: 'sw-settings-oauth2-login.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-key',
    favicon: 'icon-module-settings.svg',
    entity: 'oauth2_client',

    routes: {
        index: {
            component: 'sw-settings-oauth2-login',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'oauth2_client.viewer',
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'sw.settings.oauth2.login.index',
        icon: 'regular-key',
        privilege: 'oauth2_client.viewer',
    },
});
