/** @sw-package framework */
import './acl';
import ApprovalDesignerService from './designer/approval-designer.service';
import ApprovalFlowApiService from './service/approval-flow.api.service';
import ApprovalFlowDesignApiService from './service/approval-flow-design.api.service';
import type { SubContainer } from 'src/global.types';

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        approvalDesignerService: ApprovalDesignerService;
        approvalFlowApiService: ApprovalFlowApiService;
        approvalFlowDesignApiService: ApprovalFlowDesignApiService;
    }
}

/** @private */
Shopwell.Service().register(
    'approvalFlowApiService',
    () => new ApprovalFlowApiService(Shopwell.Application.getContainer('init').httpClient, Shopwell.Service('loginService')),
);

/** @private */
Shopwell.Service().register(
    'approvalFlowDesignApiService',
    () =>
        new ApprovalFlowDesignApiService(
            Shopwell.Application.getContainer('init').httpClient,
            Shopwell.Service('loginService'),
        ),
);

/**
 * Register a settings component and initial configuration for an exact node declaration.
 *
 * @public
 */
Shopwell.Service().register('approvalDesignerService', () => {
    const designer = new ApprovalDesignerService();
    const settingsComponent = 'sw-approval-flow-node-settings';

    designer.registerNode('core:start@1.0.0', {
        settingsComponent,
        initialConfiguration: () => ({}),
        view: (node, translate) => ({
            shape: 'terminal',
            tone: 'brand',
            summary: typeof node.config.businessType === 'string' ? translate(node.config.businessType) : undefined,
        }),
    });
    designer.registerNode('core:approval@1.0.0', {
        settingsComponent,
        initialConfiguration: () => ({
            assignees: [{ kind: 'core:users' }],
            policy: { kind: 'core:all', reject: 'veto' },
            fields: [],
            emptyAssignees: 'incident',
        }),
        view: (node, translate) => {
            const assignees = node.config.assignees as { userIds?: string[] }[] | undefined;
            const count = new Set(assignees?.flatMap((source) => source.userIds ?? [])).size;

            return {
                tone: count ? 'brand' : 'attention',
                summary: count
                    ? translate('sw-approval-flow.designer.reviewerCount', count)
                    : translate('sw-approval-flow.designer.noReviewers'),
            };
        },
    });
    designer.registerNode('core:end@1.0.0', {
        settingsComponent,
        initialConfiguration: () => ({ outcome: 'approved' }),
        view: (node, translate) => ({
            shape: 'terminal',
            tone: 'neutral',
            summary:
                typeof node.config.outcome === 'string'
                    ? translate(`sw-approval-flow.designer.${node.config.outcome}`)
                    : undefined,
        }),
    });

    return designer;
});

/** @private */
Shopwell.Component.register('sw-approval-flow-designer', () => import('./page/sw-approval-flow-designer'));
/** @private */
Shopwell.Component.register('sw-approval-flow-canvas', () => import('./component/sw-approval-flow-canvas'));
/** @private */
Shopwell.Component.register('sw-approval-flow-node-settings', () => import('./component/sw-approval-flow-node-settings'));

/** @private */
Shopwell.Component.register('sw-approval-flow-definition-list', () => import('./page/sw-approval-flow-definition-list'));
/** @private */
Shopwell.Component.register('sw-approval-flow-definition-detail', () => import('./page/sw-approval-flow-definition-detail'));
/** @private */
Shopwell.Component.register('sw-approval-flow-release-list', () => import('./component/sw-approval-flow-release-list'));

/** @private */
Shopwell.Component.register('sw-approval-flow-list', () => import('./page/sw-approval-flow-list'));
/** @private */
Shopwell.Component.register('sw-approval-flow-detail', () => import('./page/sw-approval-flow-detail'));
/** @private */
Shopwell.Component.register('sw-approval-flow-status-badge', () => import('./component/sw-approval-flow-status-badge'));

/** @private */
Shopwell.Component.register('sw-approval-flow-decision-modal', () => import('./component/sw-approval-flow-decision-modal'));

/** @private */
Shopwell.Module.register('sw-approval-flow', {
    type: 'core',
    name: 'approval-flow',
    title: 'sw-approval-flow.general.title',
    description: 'sw-approval-flow.general.description',
    color: 'var(--sw-color-module-blue-default)',
    icon: 'regular-check-circle',
    routes: {
        definitions: {
            component: 'sw-approval-flow-definition-list',
            path: 'definitions',
            meta: { privilege: 'approval_flow_definition.viewer' },
        },
        definition: {
            component: 'sw-approval-flow-definition-detail',
            path: 'definition/:id',
            props: { default: (route: { params: { id: string } }) => ({ definitionId: route.params.id }) },
            meta: { privilege: 'approval_flow_definition.viewer', parentPath: 'sw.approval.flow.definitions' },
        },
        create: {
            component: 'sw-approval-flow-designer',
            path: 'definition/create',
            meta: { privilege: 'approval_flow_definition.creator', parentPath: 'sw.approval.flow.definitions' },
        },
        designer: {
            component: 'sw-approval-flow-designer',
            path: 'definition/:id/designer',
            props: { default: (route: { params: { id: string } }) => ({ definitionId: route.params.id }) },
            meta: { privilege: 'approval_flow_definition.viewer', parentPath: 'sw.approval.flow.definitions' },
        },
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
            meta: { privilege: 'approval_flow.viewer', parentPath: 'sw.approval.flow.todo' },
        },
        started: {
            component: 'sw-approval-flow-list',
            path: 'started',
            props: { default: { view: 'started' } },
            meta: { privilege: 'approval_flow.viewer', parentPath: 'sw.approval.flow.todo' },
        },
        detail: {
            component: 'sw-approval-flow-detail',
            path: 'detail/:id',
            props: { default: (route: { params: { id: string } }) => ({ requestId: route.params.id }) },
            meta: { privilege: 'approval_flow.viewer', parentPath: 'sw.approval.flow.todo' },
        },
    },
    navigation: [
        {
            id: 'sw-approval-flow-definitions',
            path: 'sw.approval.flow.definitions',
            privilege: 'approval_flow_definition.viewer',
        },
        { id: 'sw-approval-flow' },
        { id: 'sw-approval-flow-todo', path: 'sw.approval.flow.todo', privilege: 'approval_flow.viewer' },
    ],
});
