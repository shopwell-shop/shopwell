/**
 * @sw-package fundamentals@framework
 */
import './extension/sw-admin-menu';
import './acl';
import OAuth2IdentityService from './service/oauth2-identity.api.service';

const { Module } = Shopwell;

/** @private */
Shopwell.Service().register('oauth2IdentityService', () => {
    return new OAuth2IdentityService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service().get('loginService'),
    );
});

/** @private */
Shopwell.Component.register('sw-profile-index', () => import('./page/sw-profile-index'));
/** @private */
Shopwell.Component.register('sw-profile-index-general', () => import('./view/sw-profile-index-general'));
/** @private */
Shopwell.Component.register(
    'sw-profile-index-search-preferences',
    () => import('./view/sw-profile-index-search-preferences'),
);
/** @private */
Shopwell.Component.register('sw-profile-index-oauth2-login', () => import('./view/sw-profile-index-oauth2-login'));

/**
 * @private
 */
Shopwell.Component.register(
    'sw-profile-index-privacy-preferences',
    () => import('./view/sw-profile-index-privacy-preferences'),
);

/** @private */
Module.register('sw-profile', {
    type: 'core',
    name: 'profile',
    title: 'sw-profile.general.headlineProfile',
    description: 'sw-profile.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-user',
    entity: 'user',

    routes: {
        index: {
            component: 'sw-profile-index',
            path: 'index',
            redirect: {
                name: 'sw.profile.index.general',
            },
            meta: {
                privilege: 'user.update_profile',
            },
            children: {
                general: {
                    component: 'sw-profile-index-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'user.update_profile',
                    },
                },
                searchPreferences: {
                    component: 'sw-profile-index-search-preferences',
                    path: 'search-preferences',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'user.update_profile',
                    },
                },
                privacyPreferences: {
                    component: 'sw-profile-index-privacy-preferences',
                    path: 'privacy-preferences',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'user.update_profile',
                    },
                },
                oauth2Login: {
                    component: 'sw-profile-index-oauth2-login',
                    path: 'oauth2-login',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'user.update_profile',
                    },
                },
            },
        },
    },
});
