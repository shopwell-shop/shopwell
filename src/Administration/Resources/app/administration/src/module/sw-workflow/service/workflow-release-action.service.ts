/** @sw-package framework */
/** @public */
export default class WorkflowReleaseActionService {
    private readonly components = new Map<string, string>();

    register(publisher: string, component: string): void {
        this.components.set(publisher, component);
    }

    get(publisher: string): string | null {
        return this.components.get(publisher) ?? null;
    }
}
