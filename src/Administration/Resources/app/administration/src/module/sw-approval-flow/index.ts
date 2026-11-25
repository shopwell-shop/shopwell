/** @sw-package framework */
import './acl';
import ApprovalFlowApiService from './service/approval-flow.api.service';
import type { SubContainer } from 'src/global.types';

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        approvalFlowApiService: ApprovalFlowApiService;
    }
}

/** @private */
Shopwell.Service().register(
    'approvalFlowApiService',
    () => new ApprovalFlowApiService(Shopwell.Application.getContainer('init').httpClient, Shopwell.Service('loginService')),
);

/** @private */
Shopwell.Component.register('sw-approval-flow-list', () => import('./page/sw-approval-flow-list'));
/** @private */
Shopwell.Component.register('sw-approval-flow-detail', () => import('./page/sw-approval-flow-detail'));
/** @private */
Shopwell.Component.register('sw-approval-flow-status-badge', () => import('./component/sw-approval-flow-status-badge'));

/** @private */
Shopwell.Module.register('sw-approval-flow', {
    type: 'core',
    name: 'approval-flow',
    title: 'sw-approval-flow.general.title',
    description: 'sw-approval-flow.general.description',
    color: 'var(--sw-color-module-blue-default)',
    icon: 'regular-check-circle',
    routes: {
        todo: {
            component: 'sw-approval-flow-list',
            path: 'todo',
            props: { default: { view: 'todo' } },
            meta: { privilege: 'approval_flow.viewer' },
        },
        done: {
            component: 'sw-approval-flow-list',
            path: 'done',
            props: { default: { view: 'done' } },
            meta: { privilege: 'approval_flow.viewer' },
        },
        started: {
            component: 'sw-approval-flow-list',
            path: 'started',
            props: { default: { view: 'started' } },
            meta: { privilege: 'approval_flow.viewer' },
        },
        detail: {
            component: 'sw-approval-flow-detail',
            path: 'detail/:id',
            props: { default: (route: { params: { id: string } }) => ({ requestId: route.params.id }) },
            meta: { privilege: 'approval_flow.viewer', parentPath: 'sw.approval.flow.todo' },
        },
    },
    navigation: [
        { id: 'sw-approval-flow' },
        { id: 'sw-approval-flow-todo', path: 'sw.approval.flow.todo', privilege: 'approval_flow.viewer' },
        { id: 'sw-approval-flow-done', path: 'sw.approval.flow.done', privilege: 'approval_flow.viewer' },
        { id: 'sw-approval-flow-started', path: 'sw.approval.flow.started', privilege: 'approval_flow.viewer' },
    ],
});
