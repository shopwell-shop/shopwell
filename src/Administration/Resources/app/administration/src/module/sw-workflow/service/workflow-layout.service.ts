/** @sw-package framework */
import { shallowReactive } from 'vue';
import type { WorkflowConnectionRouter, WorkflowLayout } from 'src/core/workflow/workflow-studio.types';
import { rankedWorkflowLayout } from '../util/workflow-layout.util';
import { routeWorkflowConnection } from '../util/workflow-connection-route.util';
import { registerWorkflowContribution } from './workflow-registration';

/** @public */
export type { WorkflowLayout } from 'src/core/workflow/workflow-studio.types';

type LayoutRegistration = { layout: WorkflowLayout; route: WorkflowConnectionRouter };

/** Layout and routing use the same document coordinate space. @private */
export default class WorkflowLayoutService {
    private readonly policyLayouts = shallowReactive(new Map<string, string>());

    private readonly layouts = shallowReactive(
        new Map<string, LayoutRegistration>([
            ['core:ranked', { layout: rankedWorkflowLayout, route: routeWorkflowConnection }],
        ]),
    );

    registerPolicyLayout(policy: string, layout: string): () => void {
        this.registration(layout);

        return registerWorkflowContribution(this.policyLayouts, policy, layout);
    }

    forPolicy(policy: string): WorkflowLayout {
        return this.get(this.policyLayouts.get(policy) ?? 'core:ranked');
    }

    register(name: string, layout: WorkflowLayout, route: WorkflowConnectionRouter = routeWorkflowConnection): () => void {
        return registerWorkflowContribution(this.layouts, name, { layout, route });
    }

    get(name: string): WorkflowLayout {
        return this.registration(name).layout;
    }

    connectionRouter(policy: string, layout?: string): WorkflowConnectionRouter {
        return this.registration(layout || this.policyLayouts.get(policy) || 'core:ranked').route;
    }

    private registration(name: string): LayoutRegistration {
        const registration = this.layouts.get(name);

        if (!registration) {
            throw new Error(`Unknown workflow layout: ${name}`);
        }

        return registration;
    }
}
