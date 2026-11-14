/** @sw-package framework */
import './acl';
import './service';
import './service/extension/approval-workflow.registration';

/** @private */
Shopwell.Component.register(
    'sw-workflow-users-assignee-select',
    () => import('./component/sw-workflow-users-assignee-select'),
);

/** @private */
Shopwell.Component.register(
    'sw-workflow-users-assignee-summary',
    () => import('./component/sw-workflow-users-assignee-summary'),
);

/** @private */
Shopwell.Component.register('sw-workflow-approval-settings', () => import('./component/sw-workflow-approval-settings'));

/** @private */
Shopwell.Component.register('sw-workflow-approval-node', () => import('./component/sw-workflow-approval-node'));

/** @private */
Shopwell.Component.register(
    'sw-workflow-user-assignee-select',
    () => import('./component/sw-workflow-user-assignee-select'),
);

/** @private */
Shopwell.Component.register(
    'sw-workflow-user-assignee-summary',
    () => import('./component/sw-workflow-user-assignee-summary'),
);

/** @private */
Shopwell.Component.register('sw-workflow-list', () => import('./page/sw-workflow-list'));

/** @private */
Shopwell.Component.register('sw-workflow-start-settings', () => import('./component/sw-workflow-start-settings'));

/** @private */
Shopwell.Component.register('sw-workflow-condition-settings', () => import('./component/sw-workflow-condition-settings'));

/** @private */
Shopwell.Component.register('sw-workflow-studio-editor', () => import('./component/sw-workflow-studio-editor'));

/** @private */
Shopwell.Component.register('sw-workflow-studio-toolbar', () => import('./component/sw-workflow-studio-toolbar'));

/** @private */
Shopwell.Component.register('sw-workflow-studio-node-picker', () => import('./component/sw-workflow-studio-node-picker'));

/** @private */

/** @private */
Shopwell.Component.register(
    'sw-workflow-studio-template-picker',
    () => import('./component/sw-workflow-studio-template-picker'),
);

/** @private */
Shopwell.Component.register(
    'sw-workflow-studio-node-settings',
    () => import('./component/sw-workflow-studio-node-settings'),
);

/** @private */
Shopwell.Component.register('sw-workflow-studio-canvas', () => import('./component/sw-workflow-studio-canvas'));

/** @private */
Shopwell.Component.register('sw-workflow-studio-node', () => import('./component/sw-workflow-studio-node'));

/** @private */
Shopwell.Component.register('sw-workflow-studio', () => import('./page/sw-workflow-studio'));

/** @private */
Shopwell.Component.register('sw-workflow-publish', () => import('./component/sw-workflow-publish'));
/** @private */
Shopwell.Component.register('sw-workflow-release-list', () => import('./view/sw-workflow-release-list'));

/** @private */
Shopwell.Component.register('sw-workflow-request-picker', () => import('./component/sw-workflow-request-picker'));

/** @private */
Shopwell.Component.register('sw-workflow-tasks', () => import('./page/sw-workflow-tasks'));

/** @private */
Shopwell.Component.register('sw-workflow-instance', () => import('./page/sw-workflow-instance'));

/** @private */
Shopwell.Component.register('sw-workflow-start', () => import('./component/sw-workflow-start'));

/** @private */
Shopwell.Component.register('sw-workflow-approval-actions', () => import('./component/sw-workflow-approval-actions'));

/** @private */
Shopwell.Component.register('sw-workflow-approval-timeline', () => import('./component/sw-workflow-approval-timeline'));

/** @private */
Shopwell.Component.register(
    'sw-workflow-approval-participants',
    () => import('./component/sw-workflow-approval-participants'),
);

/** @private */
Shopwell.Component.register('sw-workflow-runtime-viewer', () => import('./component/sw-workflow-runtime-viewer'));

/** @private */
Shopwell.Module.register('sw-workflow', {
    type: 'core',
    name: 'workflow',
    entity: 'workflow_draft',
    title: 'sw-workflow.general.title',
    description: 'sw-workflow.general.description',
    color: 'var(--color-shopwell-brand-500)',
    icon: 'regular-flow',
    routes: {
        tasks: { component: 'sw-workflow-tasks', path: 'tasks', meta: { privilege: 'workflow_participant.viewer' } },
        instance: {
            component: 'sw-workflow-instance',
            path: 'instance/:id',
            meta: { privilege: 'workflow_participant.viewer', parentPath: 'sw.workflow.tasks' },
        },
        releases: {
            component: 'sw-workflow-list',
            path: 'releases',
            meta: { privilege: 'workflow.viewer', parentPath: 'sw.workflow.index' },
        },
        index: { component: 'sw-workflow-list', path: 'index', meta: { privilege: 'workflow.viewer' } },
        create: { component: 'sw-workflow-studio', path: 'create', meta: { privilege: 'workflow.creator' } },
        detail: {
            component: 'sw-workflow-studio',
            path: 'detail/:id',
            meta: { privilege: 'workflow.viewer' },
            props: { default: (route: { params: Record<string, string> }) => ({ workflowId: route.params.id }) },
        },
    },
    navigation: [
        {
            id: 'sw-workflow-tasks',
            label: 'sw-workflow.runtime.navigation',
            path: 'sw.workflow.tasks',
            parent: 'sw-workflow',
            position: 25,
            privilege: 'workflow_participant.viewer',
        },
        {
            id: 'sw-workflow',
            label: 'sw-workflow.navigation.root',
            icon: 'regular-flow',
            position: 72,
        },
        {
            id: 'sw-workflow-design',
            label: 'sw-workflow.navigation.design',
            path: 'sw.workflow.index',
            parent: 'sw-workflow',
            position: 10,
            privilege: 'workflow.viewer',
        },
    ],
});
